<?php

namespace App\Services\WeighIn;

use Anthropic\Client;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads a weighing-scale display with Claude's vision. Off unless an API key is configured and the
 * weight reader in System Settings is set to "AI", because the photo leaves the server.
 */
class ClaudeScaleReader implements ScaleReader
{
    private const POUNDS_TO_KG = 0.45359237;

    private const PROMPT = <<<'TEXT'
        This photo was taken at a PNP Health Service monthly weigh-in. Somewhere in it is a weighing scale.
        Read the weight currently shown on the scale's display (digital digits or analog dial).

        Rules:
        - Report only what the scale display shows. Ignore any other numbers (QR codes, ID cards, name tags, posters, dates).
        - Many scales have several display windows, for example WEIGHT, UNIT PRICE and TOTAL PRICE. Read only the
          window labelled weight or kg; on price-computing scales it is usually the leftmost window. Ignore windows
          that show only 8888 test segments, zeros or prices.
        - Displays are often red seven-segment LEDs seen at an angle. Check digits that are easy to confuse
          (8/0/6/9, 1/7, 5/6) and the position of the decimal point before answering.
        - If the display is blank, blurred, cut off, or you cannot see a scale, set readable to false and weight to 0.
        - Give the unit shown on or next to the display: "kg", "lb", or "unknown" if not visible.
        - Confidence is "high" only when every digit and the decimal point are clearly legible.
        TEXT;

    public function __construct(private Settings $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->get('weight_reader') === 'ai' && filled(config('services.anthropic.key'));
    }

    public function read(string $jpegBytes): ScaleReading
    {
        if (! $this->isEnabled()) {
            return ScaleReading::unavailable('Automatic reading is off. Type the weight shown on the scale.');
        }

        try {
            $client = new Client(apiKey: config('services.anthropic.key'));

            $message = $client->messages->create(
                model: config('services.anthropic.scale_reader_model'),
                maxTokens: 4000,
                outputConfig: [
                    'effort' => 'low',
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'readable' => ['type' => 'boolean', 'description' => 'Whether a scale display with a weight is clearly visible'],
                                'weight' => ['type' => 'number', 'description' => 'Number shown on the scale display; 0 when not readable'],
                                'unit' => ['type' => 'string', 'enum' => ['kg', 'lb', 'unknown']],
                                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                                'displayed_text' => ['type' => 'string', 'description' => 'Exactly what the display shows'],
                                'note' => ['type' => 'string', 'description' => 'Short reason when the reading is uncertain'],
                            ],
                            'required' => ['readable', 'weight', 'unit', 'confidence', 'displayed_text', 'note'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => base64_encode($jpegBytes)]],
                        ['type' => 'text', 'text' => self::PROMPT],
                    ],
                ]],
            );
        } catch (Throwable $exception) {
            Log::warning('Scale reading failed', ['exception' => $exception->getMessage()]);

            return ScaleReading::unavailable('The weight could not be read automatically. Type the weight shown on the scale.');
        }

        if ($message->stopReason === 'refusal') {
            return ScaleReading::unavailable('The photo could not be analysed. Type the weight shown on the scale.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $this->toReading(json_decode($block->text, true));
            }
        }

        return ScaleReading::unavailable('No reading was returned. Type the weight shown on the scale.');
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function toReading(?array $data): ScaleReading
    {
        if (! is_array($data) || ($data['readable'] ?? true) === false || ! is_numeric($data['weight'] ?? null) || (float) $data['weight'] <= 0) {
            return new ScaleReading(null, 'none', $data['displayed_text'] ?? null, $data['note'] ?? 'No readable scale display was found in the photo.');
        }

        $weight = (float) $data['weight'];
        $confidence = in_array($data['confidence'] ?? null, ['high', 'medium', 'low'], true) ? $data['confidence'] : 'low';
        $note = filled($data['note'] ?? null) ? $data['note'] : null;

        if (($data['unit'] ?? null) === 'lb') {
            $weight *= self::POUNDS_TO_KG;
            $note = trim(($note ? $note.' ' : '').'Converted from pounds.');
        }

        if (($data['unit'] ?? null) === 'unknown' && $confidence === 'high') {
            $confidence = 'medium';
        }

        return new ScaleReading(round($weight, 2), $confidence, $data['displayed_text'] ?? null, $note);
    }
}
