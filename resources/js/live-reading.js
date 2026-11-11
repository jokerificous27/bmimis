/**
 * Locks a scale reading from a stream of camera frames. A weight is "locked" only after the same
 * high-confidence reading was seen in `required` frames in a row (one unreadable frame in between is
 * tolerated, e.g. motion blur). A different clear reading starts over, so a changing weight never locks.
 */
export class StableReading {
    constructor(required = 3, tolerance = 1) {
        this.required = required;
        this.tolerance = tolerance;
        this.reset();
    }

    reset() {
        this.candidate = null;
        this.count = 0;
        this.misses = 0;
        this.hint = '';
    }

    /**
     * @param {{ weight: number|null, confidence: string, text: string }|null} reading one frame's result
     * @returns {{ state: 'none'|'reading'|'locked', weight: number|null, text: string, count: number }}
     */
    push(reading) {
        const clear = reading && reading.weight !== null && reading.weight !== undefined && reading.confidence === 'high';

        if (clear) {
            if (reading.text === this.candidate?.text) {
                this.count++;
            } else {
                this.candidate = { weight: reading.weight, text: reading.text };
                this.count = 1;
            }

            this.misses = 0;
        } else {
            this.misses++;

            if (this.misses > this.tolerance) {
                this.candidate = null;
                this.count = 0;
            }
        }

        // A weight seen with low confidence is shown as a hint ("reading…") but never counts.
        this.hint = reading?.weight != null ? reading.text : '';

        return this.status();
    }

    status() {
        if (this.candidate && this.count >= this.required) {
            return { state: 'locked', weight: this.candidate.weight, text: this.candidate.text, count: this.count };
        }

        if (this.candidate || this.hint) {
            return { state: 'reading', weight: null, text: this.candidate?.text ?? this.hint, count: this.count };
        }

        return { state: 'none', weight: null, text: '', count: 0 };
    }
}
