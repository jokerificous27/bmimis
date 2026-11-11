/**
 * On-device reader for red/orange seven-segment LED weighing-scale displays.
 * Works on raw RGBA pixels ({ data, width, height }) so it runs in the browser and in Node.
 *
 * Steps: find lit LED pixels -> group them into display windows -> split a window into digits by
 * column gaps -> decide which of the 7 segments of each digit are lit -> pick the leftmost window
 * that shows a plausible weight. Returns null when nothing can be read.
 */

// Segment order: a (top), b (top right), c (bottom right), d (bottom), e (bottom left), f (top left), g (middle).
const DIGITS = {
    '1111110': '0',
    '0110000': '1',
    '1101101': '2',
    '1111001': '3',
    '0110011': '4',
    '1011011': '5',
    '1011111': '6',
    '0011111': '6',
    '1110000': '7',
    '1110010': '7',
    '1111111': '8',
    '1111011': '9',
    '1110011': '9',
};

// Sampling zones per segment as [u0, u1, v0, v1] fractions of the digit box.
const ZONES = [
    [0.3, 0.7, 0.0, 0.16],
    [0.68, 1.0, 0.14, 0.44],
    [0.68, 1.0, 0.56, 0.86],
    [0.3, 0.7, 0.84, 1.0],
    [0.0, 0.32, 0.56, 0.86],
    [0.0, 0.32, 0.14, 0.44],
    [0.3, 0.7, 0.42, 0.58],
];

const ON = 0.3;

/**
 * Thresholds tried in turn when re-reading a window: redness relative to the brightest digits, then
 * (saturated close-ups, where the glow is as red as the segments) the red channel itself.
 */
const REFINE_STEPS = [
    ['redness', 0.6],
    ['redness', 0.72],
    ['redness', 0.84],
    ['red', 0.85],
    ['red', 0.88],
    ['red', 0.91],
    ['red', 0.94],
];
const OFF = 0.12;

function isLit(data, index) {
    const r = data[index];
    const g = data[index + 1];
    const b = data[index + 2];

    return r >= 120 && g <= r * 0.78 && b <= r * 0.62;
}

function litMask({ data, width, height }) {
    const mask = new Uint8Array(width * height);

    for (let p = 0, i = 0; p < mask.length; p++, i += 4) {
        mask[p] = isLit(data, i) ? 1 : 0;
    }

    return mask;
}

/**
 * Connected components of the lit mask (8-neighbourhood), as bounding boxes with pixel counts.
 */
function components(mask, width, height) {
    const labels = new Int32Array(width * height);
    const stack = new Int32Array(width * height);
    const boxes = [];

    for (let start = 0; start < mask.length; start++) {
        if (!mask[start] || labels[start]) {
            continue;
        }

        const id = boxes.length + 1;
        const box = { x0: width, y0: height, x1: 0, y1: 0, count: 0 };
        let top = 0;
        stack[top++] = start;
        labels[start] = id;

        while (top) {
            const p = stack[--top];
            const x = p % width;
            const y = (p - x) / width;
            box.count++;
            box.x0 = Math.min(box.x0, x);
            box.x1 = Math.max(box.x1, x);
            box.y0 = Math.min(box.y0, y);
            box.y1 = Math.max(box.y1, y);

            for (let dy = -1; dy <= 1; dy++) {
                for (let dx = -1; dx <= 1; dx++) {
                    const nx = x + dx;
                    const ny = y + dy;

                    if (nx < 0 || ny < 0 || nx >= width || ny >= height) {
                        continue;
                    }

                    const q = ny * width + nx;

                    if (mask[q] && !labels[q]) {
                        labels[q] = id;
                        stack[top++] = q;
                    }
                }
            }
        }

        boxes.push(box);
    }

    return boxes;
}

/**
 * Grow the lit mask by `radius` pixels (separable box dilation), joining the segments and digits of
 * one display window into a single region while separate windows stay apart.
 */
function dilate(mask, width, height, radiusX, radiusY) {
    const horizontal = new Uint8Array(mask.length);
    const out = new Uint8Array(mask.length);

    for (let y = 0; y < height; y++) {
        let run = 0;
        const row = y * width;

        for (let x = 0; x < width + radiusX; x++) {
            run += x < width ? mask[row + x] : 0;
            run -= x - 2 * radiusX - 1 >= 0 ? mask[row + x - 2 * radiusX - 1] : 0;

            if (x - radiusX >= 0 && x - radiusX < width) {
                horizontal[row + x - radiusX] = run > 0 ? 1 : 0;
            }
        }
    }

    for (let x = 0; x < width; x++) {
        let run = 0;

        for (let y = 0; y < height + radiusY; y++) {
            run += y < height ? horizontal[y * width + x] : 0;
            run -= y - 2 * radiusY - 1 >= 0 ? horizontal[(y - 2 * radiusY - 1) * width + x] : 0;

            if (y - radiusY >= 0 && y - radiusY < height) {
                out[(y - radiusY) * width + x] = run > 0 ? 1 : 0;
            }
        }
    }

    return out;
}

/**
 * Find display windows: regions where LED segments cluster. Returns tight boxes around the lit pixels.
 */
function windows(mask, width, height, blobs) {
    if (!blobs.length) {
        return [];
    }

    // Typical LED segment thickness = median of each blob's smaller side; typical segment length =
    // a high percentile of blob heights (vertical segments, or whole digits when segments touch).
    const thickness = blobs.map((box) => Math.min(box.x1 - box.x0, box.y1 - box.y0) + 1).sort((a, b) => a - b)[Math.floor(blobs.length / 2)];
    const heights = blobs.map((box) => box.y1 - box.y0 + 1).sort((a, b) => a - b);
    const segmentLength = heights[Math.floor(heights.length * 0.8)];
    // A segment is about 40% of the digit height; joining up to ~0.75 digit heights also covers the
    // wide gap before a right-aligned "1", while separate display windows stay further apart.
    const joinX = Math.max(2, Math.round(segmentLength * 1.9));
    const regions = components(dilate(mask, width, height, joinX, Math.max(2, Math.round(thickness * 1.3))), width, height);

    // Digits like 7 or 1 light only part of their height, so neighbouring digits may not touch even
    // after dilation. Merge regions on the same line that are within the join distance.
    for (let merged = true; merged;) {
        merged = false;

        for (let i = 0; i < regions.length && !merged; i++) {
            for (let j = i + 1; j < regions.length && !merged; j++) {
                const a = regions[i];
                const b = regions[j];
                const overlapY = Math.min(a.y1, b.y1) - Math.max(a.y0, b.y0);
                const gapX = Math.max(a.x0, b.x0) - Math.min(a.x1, b.x1);

                if (overlapY > 0.5 * Math.min(a.y1 - a.y0, b.y1 - b.y0) && gapX <= joinX) {
                    regions[i] = { x0: Math.min(a.x0, b.x0), y0: Math.min(a.y0, b.y0), x1: Math.max(a.x1, b.x1), y1: Math.max(a.y1, b.y1) };
                    regions.splice(j, 1);
                    merged = true;
                }
            }
        }
    }

    return regions
        .map((region) => {
            const inside = blobs.filter((box) => box.x0 >= region.x0 && box.x1 <= region.x1 && box.y0 >= region.y0 && box.y1 <= region.y1);

            // A single wide blob can be a whole number whose digits touch because of LED glow.
            const singleWide = inside.length === 1 && inside[0].x1 - inside[0].x0 >= (inside[0].y1 - inside[0].y0) * 1.3;

            if (inside.length < 2 && !singleWide) {
                return null;
            }

            return {
                x0: Math.min(...inside.map((box) => box.x0)),
                x1: Math.max(...inside.map((box) => box.x1)),
                y0: Math.min(...inside.map((box) => box.y0)),
                y1: Math.max(...inside.map((box) => box.y1)),
            };
        })
        .filter((win) => win && win.x1 - win.x0 > (win.y1 - win.y0) * 0.9);
}

function fraction(mask, width, x0, x1, y0, y1) {
    let lit = 0;
    let total = 0;

    for (let y = Math.max(0, Math.round(y0)); y <= Math.round(y1); y++) {
        for (let x = Math.max(0, Math.round(x0)); x <= Math.round(x1); x++) {
            total++;
            lit += mask[y * width + x];
        }
    }

    return total ? lit / total : 0;
}

/**
 * Column occupancy of the given pixels after removing a slant (x shifted by slant × height above the baseline).
 */
function project(pixels, win, slant, shift) {
    const columns = new Int32Array(win.x1 - win.x0 + 1 + shift * 2);

    for (let i = 0; i < pixels.length; i += 2) {
        const column = Math.round(pixels[i] - slant * (win.y1 - pixels[i + 1])) - win.x0 + shift;

        if (column >= 0 && column < columns.length) {
            columns[column]++;
        }
    }

    return columns;
}

/**
 * Split a column run that is too wide for one digit (digits touching because of glow) at the
 * emptiest columns near the expected digit boundaries.
 */
function splitWide(run, columns, height) {
    const [c0, c1] = run;
    const width = c1 - c0 + 1;
    const pieces = Math.round(width / (height * 0.6));

    if (width <= height * 0.85 || pieces < 2) {
        return [run];
    }

    const result = [];
    let start = c0;

    for (let k = 1; k < pieces; k++) {
        const expected = Math.round(c0 + (width * k) / pieces);
        const radius = Math.max(1, Math.round(height * 0.12));
        let cut = expected;

        for (let c = expected - radius; c <= expected + radius; c++) {
            if (c > start && c < c1 && columns[c] < columns[cut]) {
                cut = c;
            }
        }

        result.push([start, cut - 1]);
        start = cut + 1;
    }

    result.push([start, c1]);

    return result;
}

/**
 * Italic LED digits lean to the right. Try slants and keep the one where the vertical strokes line
 * up best, i.e. the column projection is sharpest (largest sum of squares).
 */
function bestSlant(pixels, win, shift) {
    let best = 0;
    let bestScore = -1;

    for (let slant = -0.1; slant <= 0.3001; slant += 0.02) {
        const columns = project(pixels, win, slant, shift);
        let score = 0;

        for (const count of columns) {
            score += count * count;
        }

        if (score > bestScore * 1.0001) {
            bestScore = score;
            best = slant;
        }
    }

    return best;
}

/**
 * Read one display window: find the decimal point, split the rest into digits and decode each.
 * `glow` (per-pixel redness, optional) is used to tell lit segments from faint unlit ghost segments.
 */
function readWindow(mask, width, win, blobs, glow = null) {
    const height = win.y1 - win.y0 + 1;

    // Decimal points: small, roughly square blobs sitting on the baseline.
    const dots = blobs.filter((box) => {
        const w = box.x1 - box.x0 + 1;
        const h = box.y1 - box.y0 + 1;

        return box.x0 >= win.x0 && box.x1 <= win.x1 && box.y0 >= win.y0 && box.y1 <= win.y1
            && w <= height * 0.22 && h <= height * 0.22 && w <= h * 1.6 && h <= w * 1.6
            && box.y1 >= win.y1 - height * 0.12;
    });
    const inDot = (x, y) => dots.some((dot) => x >= dot.x0 && x <= dot.x1 && y >= dot.y0 && y <= dot.y1);

    const pixels = [];

    for (let y = win.y0; y <= win.y1; y++) {
        for (let x = win.x0; x <= win.x1; x++) {
            if (mask[y * width + x] && !inDot(x, y)) {
                pixels.push(x, y);
            }
        }
    }

    const shift = Math.ceil(0.35 * height);
    const slant = bestSlant(pixels, win, shift);
    const columns = project(pixels, win, slant, shift);

    let runs = [];
    let runStart = -1;

    for (let c = 0; c <= columns.length; c++) {
        const filled = c < columns.length && columns[c] > 0;

        if (filled && runStart < 0) {
            runStart = c;
        } else if (!filled && runStart >= 0) {
            runs.push([runStart, c - 1]);
            runStart = -1;
        }
    }

    // Segments of one digit are separated by hairline gaps. Join them, but never into something wider
    // than one digit (digits on some displays are only a few pixels apart).
    const joinGap = Math.max(1, Math.round(height * 0.07));

    for (let i = runs.length - 1; i > 0; i--) {
        if (runs[i][0] - runs[i - 1][1] - 1 <= joinGap && runs[i][1] - runs[i - 1][0] + 1 <= height * 0.72) {
            runs[i - 1][1] = runs[i][1];
            runs.splice(i, 1);
        }
    }

    runs = runs.flatMap((run) => splitWide(run, columns, height));

    // A decimal point glued to a digit by LED glow: lit pixels on the baseline that lie between the
    // upper bodies of two neighbouring digits. Each digit is also trimmed to its upper body.
    const bottomBand = win.y1 - height * 0.2;
    const columnOf = (i) => Math.round(pixels[i] - slant * (win.y1 - pixels[i + 1])) - win.x0 + shift;
    const cores = runs.map(([c0, c1]) => {
        let min = Infinity;
        let max = -Infinity;

        for (let i = 0; i < pixels.length; i += 2) {
            const column = columnOf(i);

            if (column >= c0 && column <= c1 && pixels[i + 1] < bottomBand) {
                min = Math.min(min, column);
                max = Math.max(max, column);
            }
        }

        return min === Infinity ? [c0, c1] : [min, max];
    });
    const stubDots = [];

    for (let k = 0; k + 1 < cores.length; k++) {
        const from = cores[k][1] + 1;
        const to = cores[k + 1][0] - 1;
        let count = 0;
        let sum = 0;
        let glowSum = 0;

        for (let i = 0; i < pixels.length; i += 2) {
            const column = columnOf(i);

            if (column >= from && column <= to && pixels[i + 1] >= bottomBand) {
                count++;
                sum += column;
                glowSum += glow ? glow[pixels[i + 1] * width + pixels[i]] : 255;
            }
        }

        if (count >= Math.max(4, height * height * 0.002)) {
            stubDots.push({ x: sum / count + win.x0 - shift, glow: glowSum / count });
        }
    }

    runs = cores;

    const digitMask = mask.slice();

    for (const dot of dots) {
        for (let y = dot.y0; y <= dot.y1; y++) {
            digitMask.fill(0, y * width + dot.x0, y * width + dot.x1 + 1);
        }
    }

    // Average glow of a box (used to tell lit segments and dots from faint unlit "ghost" ones).
    const meanGlow = (x0, x1, y0, y1) => {
        let sum = 0;
        let count = 0;

        for (let y = Math.max(0, Math.round(y0)); y <= Math.round(y1); y++) {
            for (let x = Math.max(0, Math.round(x0)); x <= Math.min(width - 1, Math.round(x1)); x++) {
                sum += glow ? glow[y * width + x] : mask[y * width + x] * 255;
                count++;
            }
        }

        return count ? sum / count : 0;
    };

    // Each item is a character with its (unslanted) horizontal position, to merge digits and dots in order.
    // Digits are decoded after all segment levels of the display are known.
    const items = [
        ...dots.map((dot) => ({ char: '.', x: (dot.x0 + dot.x1) / 2 - slant * (win.y1 - (dot.y0 + dot.y1) / 2), glow: meanGlow(dot.x0, dot.x1, dot.y0, dot.y1) })),
        ...stubDots.map(({ x, glow: dotGlow }) => ({ char: '.', x, glow: dotGlow })),
    ];
    const digits = [];
    let uncertain = false;

    for (const [c0, c1] of runs) {
        const ux0 = c0 + win.x0 - shift;
        const runWidth = c1 - c0 + 1;
        let top = win.y1;
        let bottom = win.y0;
        let glowSum = 0;
        let litCount = 0;

        for (let i = 0; i < pixels.length; i += 2) {
            const column = Math.round(pixels[i] - slant * (win.y1 - pixels[i + 1])) - win.x0 + shift;

            if (column >= c0 && column <= c1) {
                top = Math.min(top, pixels[i + 1]);
                bottom = Math.max(bottom, pixels[i + 1]);
                glowSum += glow ? glow[pixels[i + 1] * width + pixels[i]] : 255;
                litCount++;
            }
        }

        // Fragments shorter than a third of the digit height are noise (or a dot already counted).
        if (bottom - top + 1 < height * 0.33) {
            continue;
        }

        // Average brightness of the lit pixels: a printed border line next to the digits is dimmer.
        const item = { x: ux0 + runWidth / 2, pixelGlow: litCount ? glowSum / litCount : 0 };

        if (runWidth < height * 0.3) {
            items.push({ ...item, char: '1' });
            continue;
        }

        const segments = ZONES.map(([u0, u1, v0, v1]) => {
            const y0 = win.y0 + v0 * (height - 1);
            const y1 = win.y0 + v1 * (height - 1);
            const yMid = (y0 + y1) / 2;
            const x0 = ux0 + u0 * (runWidth - 1) + slant * (win.y1 - yMid);
            const x1 = ux0 + u1 * (runWidth - 1) + slant * (win.y1 - yMid);

            return { coverage: fraction(digitMask, width, x0, x1, y0, y1), glow: meanGlow(x0, x1, y0, y1) };
        });

        digits.push({ ...item, segments });
    }

    // Lit vs unlit: split the glow of every segment of the display into two groups. On displays with
    // faint ghost segments this separates bright (lit) from faint (unlit); on others, lit from empty.
    const levels = splitLevels(digits.flatMap((digit) => digit.segments.map((segment) => segment.glow)));
    const margin = levels ? (levels.high - levels.low) * 0.15 : 0;

    for (const digit of digits) {
        let pattern = '';

        for (const segment of digit.segments) {
            if (levels) {
                uncertain ||= Math.abs(segment.glow - levels.threshold) < margin;
                pattern += segment.glow >= levels.threshold ? '1' : '0';
            } else {
                // Every segment equally bright (e.g. "88.88"): fall back to shape coverage.
                uncertain ||= segment.coverage > OFF && segment.coverage < ON;
                pattern += segment.coverage >= (ON + OFF) / 2 ? '1' : '0';
            }
        }

        const value = DIGITS[pattern];

        if (value === undefined) {
            return null;
        }

        items.push({ x: digit.x, char: value, pixelGlow: digit.pixelGlow });
    }

    // A "1" must be about as bright as the other digits; otherwise it is a border line, not a lit digit.
    const digitGlows = items.filter((item) => item.char !== '.' && item.char !== '1').map((item) => item.pixelGlow);
    const typicalGlow = digitGlows.length ? digitGlows.reduce((sum, value) => sum + value, 0) / digitGlows.length : 0;

    if (glow && typicalGlow && items.some((item) => item.char === '1' && item.pixelGlow < typicalGlow * 0.75)) {
        return null;
    }

    // Ghost decimal points (one after every digit on some displays) are not lit: keep bright dots only.
    const lit = items.filter((item) => item.char !== '.' || !levels || item.glow >= levels.threshold);
    const text = lit.sort((a, b) => a.x - b.x).map((item) => item.char).join('').replace(/^\.+|\.+$/g, '');

    return text ? { text, uncertain, x: win.x0 } : null;
}

/**
 * Many scales print an orange or red frame around each display window. A frame is the same colour
 * family as the LEDs, so drop shapes that enclose other shapes (frames) and long thin lines (frame edges).
 * Inside one window, `minEnclosed` (a fraction of the enclosing height) keeps a "9" or "0" that only
 * encloses a decimal point.
 */
function withoutFrames(blobs, minEnclosed = 0) {
    return blobs.filter((box) => {
        const w = box.x1 - box.x0 + 1;
        const h = box.y1 - box.y0 + 1;

        if (Math.max(w, h) > 9 * Math.min(w, h)) {
            return false;
        }

        return !blobs.some((other) => other !== box
            && other.x0 > box.x0 && other.x1 < box.x1 && other.y0 > box.y0 && other.y1 < box.y1
            && other.y1 - other.y0 + 1 >= h * minEnclosed);
    });
}

/**
 * @param {{ data: Uint8ClampedArray, width: number, height: number }} image RGBA pixels
 * @param {{ min?: number, max?: number, decimals?: 1|2|null }} [range] plausible weight range in kg, and the
 *        number of decimals the scale shows (null: one or two)
 * @returns {{ weight: number|null, confidence: 'high'|'low', text: string, box: {x0: number, y0: number, x1: number, y1: number}|null }|null}
 *          `box` is where the display was found (image pixels), used to look for the QR code next to it.
 */
export function readScaleDisplay(image, range = {}) {
    const first = readWithMask(image, litMask(image), range);

    if (first?.weight != null) {
        return first;
    }

    // Second pass on strong redness only. Coloured panels and frames around the display windows
    // (orange, dark red) pass the colour test above and can join the digits into one shape; they are
    // much less red than lit LED segments.
    const second = readWithMask(image, rednessMask(image), range);

    return second?.weight != null ? second : first;
}

/**
 * Pixels whose redness (red minus the larger of green and blue) is close to the reddest pixels of the photo.
 */
function rednessMask({ data, width, height }) {
    const redness = new Uint8Array(width * height);
    const histogram = new Uint32Array(256);

    for (let p = 0, i = 0; p < redness.length; p++, i += 4) {
        // Red LEDs have about as much green as blue; orange paint has far more green than blue.
        const orange = data[i + 1] - data[i + 2] > data[i] * 0.15;
        const value = orange ? 0 : Math.max(0, data[i] - Math.max(data[i + 1], data[i + 2]));
        redness[p] = value;
        histogram[value]++;
    }

    // The brightest lit segments: the 99.9th percentile of redness (LEDs cover well over 0.1% of a weigh-in photo).
    let seen = 0;
    let peak = 255;

    for (let value = 255; value >= 0; value--) {
        seen += histogram[value];

        if (seen >= redness.length * 0.001) {
            peak = value;
            break;
        }
    }

    const threshold = Math.max(60, peak * 0.6);
    const mask = new Uint8Array(redness.length);

    for (let p = 0; p < mask.length; p++) {
        mask[p] = redness[p] >= threshold ? 1 : 0;
    }

    return mask;
}

function readWithMask(image, mask, range) {
    const { width, height } = image;
    const min = range.min ?? 30;
    const max = range.max ?? 250;
    const minArea = Math.max(4, Math.round((width * height) / 400000));
    const blobs = withoutFrames(components(mask, width, height).filter((box) => box.count >= minArea));

    // Group only the kept blobs into windows: a dropped frame (e.g. an orange panel around all the
    // display windows) would otherwise join every window into one.
    const blobMask = new Uint8Array(mask.length);

    for (const box of blobs) {
        for (let y = box.y0; y <= box.y1; y++) {
            for (let x = box.x0, p = y * width + box.x0; x <= box.x1; x++, p++) {
                blobMask[p] = mask[p];
            }
        }
    }

    // Body-weight scales show one or two decimals; a third one means a stray mark was read as a digit.
    const pattern = range.decimals === 1 || range.decimals === 2 ? new RegExp(`^\\d+\\.\\d{${range.decimals}}$`) : /^\d+\.\d{1,2}$/;
    // Only 8s (every segment lit: unlit "ghost" segments, or the display's self-test) or only 1s (frame
    // lines) are never trusted; staff type such a weight.
    const isWeight = (reading) => reading && pattern.test(reading.text) && !/^[8.]+$/.test(reading.text) && !/^[1.]+$/.test(reading.text)
        && Number(reading.text) >= min && Number(reading.text) <= max;

    // Close-up photos catch the glow around the LEDs, which fuses the digits into one shape. When a
    // window does not read as a weight, read it again keeping only brighter pixels (REFINE_STEPS).
    const results = windows(blobMask, width, height, blobs).map((win) => {
        let first = null;

        for (const [measure, level] of REFINE_STEPS) {
            const local = refine(image, win, level, measure);
            const reading = local ? readWindow(local.mask, local.width, local.win, local.blobs, local.glow) : null;
            first ??= reading;

            if (isWeight(reading)) {
                return { win, reading };
            }
        }

        return { win, reading: first };
    });

    // On multi-window scales (weight, unit price, total) the weight is the leftmost window of the row.
    // A reading with another display window just to its left is never used, so a price cannot be
    // saved as a weight even when the weight window itself is unreadable.
    // An LED window is mostly red (lit segments and their glow); red shapes elsewhere in a photo (skin,
    // clothing, signs) are scattered over a mostly non-red area.
    const accepted = results.filter((result) => isWeight(result.reading)
        && !results.some((other) => other !== result && isLeftNeighbour(other.win, result.win))
        && redShare(image, result.win) >= 0.2);
    const weights = [...new Set(accepted.map((result) => result.reading.text))];

    if (weights.length === 1) {
        const certain = accepted.every((result) => !result.reading.uncertain);

        return { weight: Number(weights[0]), confidence: certain ? 'high' : 'low', text: weights[0], box: accepted[0].win };
    }

    const seen = results.find((result) => result.reading?.text);

    return results.length ? { weight: null, confidence: 'low', text: seen?.reading.text ?? '', box: seen?.win ?? null } : null;
}

/**
 * Share of clearly red pixels (red at least 40 above green and blue) inside a box of the image.
 */
function redShare({ data, width }, box) {
    let red = 0;
    let total = 0;

    for (let y = box.y0; y <= box.y1; y++) {
        for (let x = box.x0, i = (y * width + box.x0) * 4; x <= box.x1; x++, i += 4) {
            total++;
            red += data[i] - Math.max(data[i + 1], data[i + 2]) >= 40 ? 1 : 0;
        }
    }

    return total ? red / total : 0;
}

/**
 * Whether `other` is a display window of similar size on the same row, directly left of `win`.
 */
function isLeftNeighbour(other, win) {
    const heightA = other.y1 - other.y0 + 1;
    const heightB = win.y1 - win.y0 + 1;
    const overlap = Math.min(other.y1, win.y1) - Math.max(other.y0, win.y0);

    return other.x1 < win.x0
        && overlap > 0.5 * Math.min(heightA, heightB)
        && heightA / heightB > 0.6 && heightA / heightB < 1.6;
}

/**
 * Re-threshold a candidate window on "redness" relative to its brightest digits. This drops the
 * orange glow around LEDs (which joins neighbouring digits) and the printed frame around the window.
 *
 * `measure` "red" uses the red channel of reddish pixels instead: in close-ups the camera saturates,
 * the glow is then about as "red" as the segments but clearly less bright.
 */
function refine(image, win, level = 0.6, measure = 'redness') {
    const pad = Math.round((win.y1 - win.y0) * 0.15) + 2;
    const ox = Math.max(0, win.x0 - pad);
    const oy = Math.max(0, win.y0 - pad);
    const width = Math.min(image.width, win.x1 + pad + 1) - ox;
    const height = Math.min(image.height, win.y1 + pad + 1) - oy;
    const redness = new Int16Array(width * height);
    const histogram = new Uint32Array(256);

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const i = ((y + oy) * image.width + x + ox) * 4;
            const red = image.data[i];
            const other = Math.max(image.data[i + 1], image.data[i + 2]);
            const value = measure === 'red' ? (red - other >= 60 ? red : 0) : Math.max(0, red - other);
            redness[y * width + x] = value;
            histogram[value]++;
        }
    }

    // Brightness of the digits: the 97th percentile of redness in the window.
    let seen = 0;
    let peak = 255;

    for (let value = 255; value >= 0; value--) {
        seen += histogram[value];

        if (seen >= redness.length * 0.03) {
            peak = value;
            break;
        }
    }

    // Shape mask: generous on purpose. On scales whose unlit "ghost" segments glow faintly, the ghosts
    // are included here (they help locate every digit); lit vs unlit is decided per segment later.
    const threshold = Math.max(40, peak * level);
    const mask = new Uint8Array(width * height);

    for (let p = 0; p < mask.length; p++) {
        mask[p] = redness[p] >= threshold ? 1 : 0;
    }

    const blobs = withoutFrames(components(mask, width, height).filter((box) => box.count >= 3), 0.25);

    if (!blobs.length) {
        return null;
    }

    // Keep blobs that share the main text line (drop stray specks of the frame above or below).
    // Vertical segments define the line; horizontal top/bottom bars sit just above and below it.
    // Pieces of a frame left and right of the digits reach clearly above and below several digits:
    // drop shapes that vertically surround at least two other sizeable shapes.
    const heightOf = (box) => box.y1 - box.y0 + 1;
    const shapes = blobs.filter((box) => {
        const inset = heightOf(box) * 0.1;
        const surrounded = blobs.filter((other) => other !== box
            && (other.x1 < box.x0 || other.x0 > box.x1)
            && other.y0 > box.y0 + inset && other.y1 < box.y1 - inset
            && heightOf(other) >= heightOf(box) * 0.4);

        return surrounded.length < 2;
    });

    if (!shapes.length) {
        return null;
    }

    // The text line comes from the thicker shapes (digits, segments). A thin vertical line, such as the
    // window's own border, must not set the line, and is dropped when it reaches clearly beyond it.
    const isThin = (box) => (box.x1 - box.x0 + 1) * 5 <= heightOf(box);
    const thick = shapes.filter((box) => !isThin(box));
    const lineShapes = thick.length ? thick : shapes;
    const tallest = Math.max(...lineShapes.map(heightOf));
    const line = lineShapes.filter((box) => heightOf(box) >= tallest * 0.5);
    const lineTop = Math.min(...line.map((box) => box.y0));
    const lineBottom = Math.max(...line.map((box) => box.y1));
    const margin = (lineBottom - lineTop + 1) * 0.35;
    const thinMargin = (lineBottom - lineTop + 1) * 0.12;
    // Shapes reaching above or below the line (top and bottom bars of digits) must be at least as thick
    // as a segment bar; one-pixel specks of the frame beside the digits would stretch the digit height.
    const barThickness = Math.max(2, (lineBottom - lineTop + 1) * 0.06);
    const overDigit = (box) => Math.min(box.x1 - box.x0, box.y1 - box.y0) + 1 >= barThickness;
    const kept = shapes.filter((box) => {
        const withinBand = isThin(box) && thick.length
            ? box.y0 >= lineTop - thinMargin && box.y1 <= lineBottom + thinMargin
            : box.y0 >= lineTop - margin && box.y1 <= lineBottom + margin;
        const insideLine = box.y0 >= lineTop && box.y1 <= lineBottom;

        return withinBand && (insideLine || overDigit(box));
    });
    const top = Math.min(...kept.map((box) => box.y0));
    const bottom = Math.max(...kept.map((box) => box.y1));
    const keptMask = new Uint8Array(mask.length);

    for (const box of kept) {
        for (let y = box.y0; y <= box.y1; y++) {
            for (let x = box.x0; x <= box.x1; x++) {
                keptMask[y * width + x] = mask[y * width + x];
            }
        }
    }

    return {
        mask: keptMask,
        width,
        blobs: kept,
        glow: redness,
        win: { x0: Math.min(...kept.map((box) => box.x0)), x1: Math.max(...kept.map((box) => box.x1)), y0: top, y1: bottom },
    };
}

/**
 * Split the glow levels of all segments of one display into lit and unlit (Otsu's method).
 * Returns { threshold, low, high } with the mean of each group, or null when every segment is lit
 * (one group only: the darker group is nearly as bright as the brighter one).
 */
function splitLevels(values) {
    const sorted = [...values].sort((a, b) => a - b);
    const total = sorted.reduce((sum, value) => sum + value, 0);
    let best = null;
    let below = 0;

    for (let i = 1; i < sorted.length; i++) {
        below += sorted[i - 1];
        const low = below / i;
        const high = (total - below) / (sorted.length - i);
        const variance = i * (sorted.length - i) * (high - low) ** 2;

        if (!best || variance > best.variance) {
            best = { variance, threshold: (sorted[i - 1] + sorted[i]) / 2, low, high };
        }
    }

    if (!best || best.high <= 0 || best.low > best.high * 0.75) {
        return null;
    }

    return best;
}
