/**
 * Finds places in a photo that look like a QR code: compact patches packed with sharp light/dark
 * edges (a QR on a phone screen or printed card). The QR decoder only succeeds on a tight, enlarged
 * crop when the code is small in the frame, so these candidates are cropped and decoded one by one.
 * Works on raw RGBA pixels ({ data, width, height }) so it runs in the browser and in Node.
 *
 * @returns {Array<{x0: number, y0: number, x1: number, y1: number}>} candidate boxes, most likely first
 */
export function locateQrCandidates({ data, width, height }, limit = 6) {
    const block = Math.max(6, Math.round(Math.max(width, height) / 90));
    const cols = Math.ceil(width / block);
    const rows = Math.ceil(height / block);
    const density = new Float32Array(cols * rows);
    const grey = (x, y) => {
        const i = (y * width + x) * 4;

        return Math.min(data[i], data[i + 1], data[i + 2]);
    };

    // Share of pixels in each block with a strong horizontal or vertical edge.
    for (let by = 0; by < rows; by++) {
        for (let bx = 0; bx < cols; bx++) {
            let edges = 0;
            let total = 0;

            for (let y = by * block; y < Math.min(height - 1, (by + 1) * block); y += 2) {
                for (let x = bx * block; x < Math.min(width - 1, (bx + 1) * block); x += 2) {
                    const g = grey(x, y);
                    total++;

                    if (Math.abs(g - grey(x + 1, y)) > 45 || Math.abs(g - grey(x, y + 1)) > 45) {
                        edges++;
                    }
                }
            }

            density[by * cols + bx] = total ? edges / total : 0;
        }
    }

    // Group neighbouring busy blocks; QR patches are compact and roughly square.
    const busy = (index) => density[index] >= 0.16;
    const seen = new Uint8Array(cols * rows);
    const candidates = [];

    for (let start = 0; start < density.length; start++) {
        if (seen[start] || !busy(start)) {
            continue;
        }

        const stack = [start];
        seen[start] = 1;
        let minX = cols;
        let minY = rows;
        let maxX = 0;
        let maxY = 0;
        let count = 0;
        let score = 0;

        while (stack.length) {
            const index = stack.pop();
            const bx = index % cols;
            const by = (index - bx) / cols;
            minX = Math.min(minX, bx);
            maxX = Math.max(maxX, bx);
            minY = Math.min(minY, by);
            maxY = Math.max(maxY, by);
            count++;
            score += density[index];

            for (let dy = -1; dy <= 1; dy++) {
                for (let dx = -1; dx <= 1; dx++) {
                    const nx = bx + dx;
                    const ny = by + dy;
                    const next = ny * cols + nx;

                    if (nx >= 0 && ny >= 0 && nx < cols && ny < rows && !seen[next] && busy(next)) {
                        seen[next] = 1;
                        stack.push(next);
                    }
                }
            }
        }

        const w = maxX - minX + 1;
        const h = maxY - minY + 1;
        const fill = count / (w * h);

        if (count < 4 || w > cols * 0.5 || h > rows * 0.5 || w / h > 2.5 || h / w > 2.5 || fill < 0.35) {
            continue;
        }

        // Pad by a quarter on each side so the quiet zone and finder patterns are inside the crop.
        const padX = Math.max(1, Math.round(w * 0.25));
        const padY = Math.max(1, Math.round(h * 0.25));

        candidates.push({
            x0: Math.max(0, (minX - padX) * block),
            y0: Math.max(0, (minY - padY) * block),
            x1: Math.min(width, (maxX + 1 + padX) * block),
            y1: Math.min(height, (maxY + 1 + padY) * block),
            score: (score / count) * fill,
        });
    }

    return candidates
        .sort((a, b) => b.score - a.score)
        .slice(0, limit)
        .map(({ x0, y0, x1, y1 }) => ({ x0, y0, x1, y1 }));
}
