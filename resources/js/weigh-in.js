import jsQR from 'jsqr';
import QRCode from 'qrcode';
import { locateQrCandidates } from './qr-locator';
import { StableReading } from './live-reading';
import { readScaleDisplay } from './seven-segment';

const QR_PREFIX = 'PNPBMI:';

/**
 * Regions to search, as fractions of the photo [x, y, width, height]. The whole frame first, then the
 * areas where a printed card usually sits (in front of the scale, lower part of the photo), enlarged.
 */
const SEARCH_REGIONS = [
    [0, 0, 1, 1],
    [0, 0.5, 1, 0.5],
    [0.2, 0.55, 0.6, 0.45],
    [0, 0.25, 1, 0.5],
    [0, 0, 1, 0.5],
];

/**
 * Find a personnel weigh-in QR code in an image. A QR that is small in the frame (phone in a holder
 * beside the scale, in a full-body photo) only decodes from a tight, enlarged crop, so the order is:
 * located QR-like spots (`spots`), the whole frame, the area around the scale display (`focus`),
 * then larger regions. Each attempt is [region as fractions of the photo, target edge in px, max zoom].
 */
async function decodeQrFromSource(source, width, height, quick = false, focus = [], spots = []) {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    let spotsAdded = false;
    const attempts = quick
        ? [[SEARCH_REGIONS[0], 1000, 4], ...focus.map((region) => [region, 900, 6])]
        : [
              ...spots.flatMap((region) => [[region, 800, 8], [region, 500, 8], [region, 1100, 8]]),
              [SEARCH_REGIONS[0], 1200, 4],
              ...focus.flatMap((region) => [[region, 1400, 4], [region, 2000, 4]]),
              [SEARCH_REGIONS[0], 1800, 4],
              [SEARCH_REGIONS[0], 2600, 4],
              ...SEARCH_REGIONS.slice(1).map((region) => [region, 1600, 4]),
          ];

    for (const [[rx, ry, rw, rh], maxEdge, maxZoom] of attempts) {
        const sx = Math.round(width * rx);
        const sy = Math.round(height * ry);
        const sw = Math.max(1, Math.round(width * rw));
        const sh = Math.max(1, Math.round(height * rh));
        const scale = Math.min(maxEdge / Math.max(sw, sh), maxZoom);
        canvas.width = Math.round(sw * scale);
        canvas.height = Math.round(sh * scale);
        context.drawImage(source, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);

        const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(pixels.data, pixels.width, pixels.height, { inversionAttempts: 'attemptBoth' });

        if (code?.data?.startsWith(QR_PREFIX)) {
            return code.data;
        }

        // Live camera: after the quick whole-frame try, zoom into QR-like spots of that same frame.
        if (quick && !spotsAdded) {
            spotsAdded = true;
            const scaleBack = sw / pixels.width;

            for (const box of locateQrCandidates(pixels, 3)) {
                const region = [
                    (box.x0 * scaleBack) / width,
                    (box.y0 * scaleBack) / height,
                    ((box.x1 - box.x0) * scaleBack) / width,
                    ((box.y1 - box.y0) * scaleBack) / height,
                ];
                attempts.push([region, 700, 8]);
            }
        }
    }

    return null;
}

/**
 * Read the scale display inside a region of an image or video frame. The region (fractions of the
 * frame) is drawn at up to `maxEdge` pixels, so a small display in a large photo keeps its full detail.
 * The returned box is in fractions of the whole frame.
 */
function readRegion(source, width, height, [rx, ry, rw, rh], range, maxEdge) {
    const sx = Math.max(0, Math.round(width * rx));
    const sy = Math.max(0, Math.round(height * ry));
    const sw = Math.max(1, Math.min(width - sx, Math.round(width * rw)));
    const sh = Math.max(1, Math.min(height - sy, Math.round(height * rh)));
    const scale = Math.min(1, maxEdge / Math.max(sw, sh));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(sw * scale));
    canvas.height = Math.max(1, Math.round(sh * scale));
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.drawImage(source, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
    const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
    const reading = readScaleDisplay(pixels, range);

    if (!reading) {
        return { reading: null, pixels };
    }

    const box = reading.box
        ? {
              x0: (sx + reading.box.x0 / scale) / width,
              x1: (sx + reading.box.x1 / scale) / width,
              y0: (sy + reading.box.y0 / scale) / height,
              y1: (sy + reading.box.y1 / scale) / height,
          }
        : null;

    return { reading: { ...reading, box }, pixels };
}

/**
 * The area around a display box that the Smart camera reads after a tap: about a third of the frame
 * width, centred on the display (as fractions of the frame).
 */
function spotAround(box, width, height) {
    const w = 0.34;
    const h = Math.min(0.5, (w * width) / height / 2);
    const cx = (box.x0 + box.x1) / 2;
    const cy = (box.y0 + box.y1) / 2;

    return [Math.max(0, Math.min(1 - w, cx - w / 2)), Math.max(0, Math.min(1 - h, cy - h / 2)), w, h];
}

const isClear = (reading) => reading?.weight != null && reading.confidence === 'high';

/**
 * Read the scale display from a whole photo or frame: first scaled down to `overview` pixels, then, when
 * that is not a clear reading, the display area again at full resolution (digits 2–3× larger).
 *
 * For a single photo (`photo`), a clear reading must be confirmed by a second, independent read of the
 * area around the display; otherwise it is downgraded to low confidence so staff check it. Live frames
 * skip this: the Smart camera instead needs the same reading in several frames in a row.
 */
function readDisplay(source, width, height, range, overview = 2000, photo = true) {
    const first = readRegion(source, width, height, [0, 0, 1, 1], range, overview);
    let reading = first.reading;

    if (!isClear(reading) && Math.max(width, height) > overview * 1.2 && (reading?.box || photo)) {
        const detail = reading?.box
            ? readRegion(source, width, height, spotAround(reading.box, width, height), range, 2400).reading
            : readRegion(source, width, height, [0, 0, 1, 1], range, Math.min(4000, Math.max(width, height))).reading;

        if (detail?.weight != null) {
            reading = detail;
        }
    }

    if (photo && isClear(reading) && reading.box) {
        const check = readRegion(source, width, height, spotAround(reading.box, width, height), range, 1400).reading;

        if (check?.text !== reading.text) {
            reading = { ...reading, confidence: 'low' };
        }
    }

    return { display: reading, pixels: first.pixels };
}

/**
 * Regions around the display where the phone holder (QR code) usually is, as fractions of the frame.
 */
function qrFocusRegions(box) {
    if (!box) {
        return [];
    }

    // The weight window is only part of the scale head; the phone holder sits beside the head,
    // so search about five display-widths to each side and three above and below, plus a tighter
    // band at display height (enlarged more, for very small QR codes).
    const unitX = Math.max(box.x1 - box.x0, (box.y1 - box.y0) * 2);
    const unitY = unitX * 0.6;
    const left = Math.max(0, box.x0 - unitX * 5);
    const right = Math.min(1, box.x1 + unitX * 5);
    const top = Math.max(0, box.y0 - unitY * 3);
    const bottom = Math.min(1, box.y1 + unitY * 3);
    const bandTop = Math.max(0, box.y0 - unitY * 1.5);
    const bandBottom = Math.min(1, box.y1 + unitY * 1.5);

    return [
        [left, top, right - left, bottom - top],
        [left, bandTop, right - left, bandBottom - bandTop],
    ];
}

/**
 * Read the QR code and the scale's LED display from one photo, entirely on this device.
 */
async function analysePhoto(file, range) {
    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });

        // 1. Read the scale display.
        const { display, pixels } = readDisplay(bitmap, bitmap.width, bitmap.height, range);

        // 2. Find the QR code: first at spots that look like a QR, then around the display (the phone holder).
        const spots = locateQrCandidates(pixels).map(({ x0, y0, x1, y1 }) => [
            x0 / pixels.width,
            y0 / pixels.height,
            (x1 - x0) / pixels.width,
            (y1 - y0) / pixels.height,
        ]);
        const payload = await decodeQrFromSource(bitmap, bitmap.width, bitmap.height, false, qrFocusRegions(display?.box), spots);

        return { payload, display, size: [bitmap.width, bitmap.height] };
    } catch {
        return { payload: null, display: null, size: [0, 0] };
    }
}

const REGION_KEY = 'weighIn.displaySpot';

function storedRegion() {
    try {
        const value = JSON.parse(localStorage.getItem(REGION_KEY) ?? 'null');

        return Array.isArray(value) && value.length === 4 ? value : null;
    } catch {
        return null;
    }
}

/**
 * Alpine component for the Weigh-in Station. The QR code and the scale display are read in the browser;
 * the photo is then uploaded through Livewire for storage (and optional server-side reading).
 *
 * Smart camera: the live camera reads every frame and shows whether the QR code and the weight were
 * picked up. Capture is only possible once the same weight was read clearly in several frames in a row.
 */
window.weighInStation = (options = {}) => ({
    scanning: false,
    stream: null,
    message: '',
    readOnDevice: !!options.readOnDevice,
    range: { min: options.minWeight ?? 30, max: options.maxWeight ?? 250, decimals: options.decimals ?? null },
    liveCameraSupported: window.isSecureContext && !!navigator.mediaDevices?.getUserMedia,
    secure: window.isSecureContext,
    lock: new StableReading(3),
    live: { state: 'none', weight: null, text: '', count: 0 },
    displayBox: null,
    displaySpot: storedRegion(),
    personName: '',
    identifying: false,
    capturing: false,
    autoCapture: (() => {
        try {
            return localStorage.getItem('weighIn.autoCapture') !== 'off';
        } catch {
            return true;
        }
    })(),
    frameCount: 0,
    framesWithoutBox: 0,
    overlay: { display: null, spot: null },

    get identified() {
        return !!this.personName || !!this.$wire.personnelId;
    },

    /**
     * Capture is allowed once the personnel is identified and, when the phone reads the weight, the weight is locked.
     */
    get ready() {
        return this.identified && (!this.readOnDevice || this.live.state === 'locked');
    },

    async handleFile(event) {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (file) {
            await this.process(file);
        }
    },

    async process(file) {
        this.message = this.readOnDevice ? 'Reading the QR code and the scale…' : 'Looking for the QR code…';
        const { payload, display, size } = await analysePhoto(file, this.range);

        if (this.readOnDevice) {
            await this.$wire.useDeviceReading(display?.weight ?? null, display?.confidence ?? 'none', display?.text ?? '', size[0], size[1]);
        }

        if (payload) {
            await this.$wire.identify(payload);
            this.message = '';
        } else if (!this.$wire.personnelId) {
            this.message = 'No QR code found in the photo. Search the personnel by name, or retake the photo with the QR code clearly visible.';
        } else {
            this.message = '';
        }

        this.upload(file);
    },

    upload(file) {
        this.$wire.upload(
            'photo',
            file,
            () => {},
            () => (this.message = 'The photo could not be uploaded. Please try again.'),
        );
    },

    async startScan() {
        try {
            // Ask for the highest resolution the camera offers: the display is small in a whole-body frame.
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 3840 }, height: { ideal: 2160 } },
                audio: false,
            });
        } catch {
            this.message = 'The camera could not be opened. Use "Take photo" instead.';

            return;
        }

        await this.tuneCamera();

        this.scanning = true;
        this.lock.reset();
        this.live = this.lock.status();
        this.personName = '';
        this.message = '';
        this.$refs.video.srcObject = this.stream;
        await this.$refs.video.play();
        this.updateOverlay();
        this.scanLoop();
    },

    /**
     * Where the camera supports it: continuous focus, and exposure slightly darker so bright LED digits
     * are not overexposed (unlit "ghost" segments stay dark).
     */
    async tuneCamera(point = null) {
        const track = this.stream?.getVideoTracks()[0];
        const capabilities = track?.getCapabilities?.() ?? {};
        const advanced = {};

        if (capabilities.focusMode?.includes('continuous')) {
            advanced.focusMode = 'continuous';
        }

        if (capabilities.exposureMode?.includes('continuous')) {
            advanced.exposureMode = 'continuous';
        }

        if (capabilities.exposureCompensation) {
            const { min, max } = capabilities.exposureCompensation;
            advanced.exposureCompensation = Math.max(min, Math.min(max, -1));
        }

        if (point && 'pointsOfInterest' in capabilities) {
            advanced.pointsOfInterest = [point];
        }

        if (Object.keys(advanced).length) {
            try {
                await track.applyConstraints({ advanced: [advanced] });
            } catch {
                // Not supported on this camera: keep the defaults.
            }
        }
    },

    async scanLoop() {
        if (!this.scanning || this.capturing) {
            return;
        }

        const video = this.$refs.video;
        const started = performance.now();

        if (video.readyState >= 2 && video.videoWidth) {
            const width = video.videoWidth;
            const height = video.videoHeight;
            let display = null;
            this.frameCount++;

            if (this.readOnDevice || !this.identified) {
                // Read where staff tapped the display; every other frame, also look over the whole frame
                // in case the phone or the spot moved.
                if (this.displaySpot) {
                    display = readRegion(video, width, height, this.displaySpot, this.range, 1400).reading;
                }

                if (!isClear(display) && (!this.displaySpot || this.frameCount % 2 === 0)) {
                    display = readDisplay(video, width, height, this.range, 1600, false).display ?? display;
                }
            }

            // Keep the box on screen through a few unreadable frames so it does not flicker.
            if (display?.box) {
                this.displayBox = display.box;
                this.framesWithoutBox = 0;
            } else if (++this.framesWithoutBox >= 5) {
                this.displayBox = null;
            }

            this.live = this.lock.push(display);

            if (!this.identified && !this.identifying) {
                const payload = await decodeQrFromSource(video, width, height, true, qrFocusRegions(this.displayBox));

                if (payload) {
                    this.identifying = true;
                    this.personName = (await this.$wire.identify(payload)) ?? '';
                    this.identifying = false;
                }
            }

            this.updateOverlay();

            if (this.readOnDevice && this.autoCapture && this.ready) {
                this.captureFrame();

                return;
            }
        }

        // Keep the phone responsive: wait at least as long as the last frame took to read.
        setTimeout(() => this.scanLoop(), Math.max(120, performance.now() - started));
    },

    /**
     * Staff tap the scale display on the screen: read only that spot from now on (remembered on this
     * phone, the scale does not move) and focus / expose for it.
     */
    tapDisplay(event) {
        const video = this.$refs.video;
        const content = this.contentRect();

        if (!content) {
            return;
        }

        const rect = video.getBoundingClientRect();
        const x = (event.clientX - rect.left - content.left) / content.width;
        const y = (event.clientY - rect.top - content.top) / content.height;

        if (x < 0 || x > 1 || y < 0 || y > 1) {
            return;
        }

        // About a third of the frame width wide; height in proportion for portrait and landscape.
        const w = 0.34;
        const h = Math.min(0.5, (w * video.videoWidth) / video.videoHeight / 2);
        this.displaySpot = [Math.max(0, Math.min(1 - w, x - w / 2)), Math.max(0, Math.min(1 - h, y - h / 2)), w, h];
        this.lock.reset();

        try {
            localStorage.setItem(REGION_KEY, JSON.stringify(this.displaySpot));
        } catch {
            // Private mode: the spot is kept until the page is closed.
        }

        this.tuneCamera({ x, y });
        this.updateOverlay();
    },

    clearDisplaySpot() {
        this.displaySpot = null;
        this.lock.reset();

        try {
            localStorage.removeItem(REGION_KEY);
        } catch {
            // Nothing stored.
        }

        this.updateOverlay();
    },

    toggleAutoCapture() {
        this.autoCapture = !this.autoCapture;

        try {
            localStorage.setItem('weighIn.autoCapture', this.autoCapture ? 'on' : 'off');
        } catch {
            // Not remembered.
        }
    },

    /**
     * The area of the <video> element that shows the picture (object-contain leaves bars).
     */
    contentRect() {
        const video = this.$refs.video;

        if (!video?.videoWidth || !video.clientWidth) {
            return null;
        }

        const scale = Math.min(video.clientWidth / video.videoWidth, video.clientHeight / video.videoHeight);
        const width = video.videoWidth * scale;
        const height = video.videoHeight * scale;

        return { left: (video.clientWidth - width) / 2, top: (video.clientHeight - height) / 2, width, height };
    },

    updateOverlay() {
        const content = this.contentRect();
        const place = (box) =>
            box && content
                ? {
                      left: `${content.left + box.x0 * content.width}px`,
                      top: `${content.top + box.y0 * content.height}px`,
                      width: `${(box.x1 - box.x0) * content.width}px`,
                      height: `${(box.y1 - box.y0) * content.height}px`,
                  }
                : null;
        const spot = this.displaySpot;

        this.overlay = {
            display: place(this.displayBox),
            spot: place(spot ? { x0: spot[0], y0: spot[1], x1: spot[0] + spot[2], y1: spot[1] + spot[3] } : null),
        };
    },

    async captureFrame() {
        if (!this.ready || this.capturing) {
            return;
        }

        this.capturing = true;
        const locked = { ...this.live };
        const video = this.$refs.video;
        const canvas = this.$refs.canvas;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        navigator.vibrate?.(80);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
        this.stopScan();
        this.capturing = false;

        if (this.readOnDevice) {
            await this.$wire.useDeviceReading(locked.weight, 'high', locked.text, canvas.width, canvas.height, locked.count);
        }

        this.message = '';
        this.upload(new File([blob], 'weigh-in.jpg', { type: 'image/jpeg' }));
    },

    stopScan() {
        this.scanning = false;
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
        this.displayBox = null;
    },

    destroy() {
        this.stopScan();
    },
});

/**
 * Draw a personnel weigh-in QR code into a <canvas>.
 */
window.renderWeighInQr = (canvas, payload, size = 220) =>
    QRCode.toCanvas(canvas, payload, { width: size, margin: 2, errorCorrectionLevel: 'M' });
