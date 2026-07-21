/**
 * ShopMGR merchant admin — small Alpine components and vanilla-JS helpers used
 * across the admin views. Loaded once from the layout (cache-busted via
 * asset_v()). No external chart/UI libraries — everything here is hand-rolled
 * SVG or plain DOM.
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ *
     * Alpine components
     * ------------------------------------------------------------------ */
    document.addEventListener('alpine:init', function () {

        /**
         * Repeatable variant row editor for the product form's
         * "Variants & Pricing" tab. `initial` is the JSON-serialised list of
         * existing variants (edit) or an empty array (create); rows post as
         * variants[N][field] and are read verbatim by
         * ProductController::syncVariants().
         */
        Alpine.data('variantRepeater', function (initial) {
            return {
                rows: (initial && initial.length) ? initial : [blankVariant()],

                addRow: function () {
                    this.rows.push(blankVariant());
                },

                removeRow: function (index) {
                    if (this.rows.length > 1) {
                        this.rows.splice(index, 1);
                    }
                },

                /** Header label for a row: the option values, or a fallback. */
                rowLabel: function (row, index) {
                    var parts = [row.option1_value, row.option2_value, row.option3_value].filter(Boolean);
                    return parts.length ? parts.join(' / ') : ('Variant ' + (index + 1));
                },
            };
        });

        /**
         * Renders the dashboard's 30-day sales sparkline as a bare SVG
         * polyline/polygon — no charting library. `series` is
         * [{date, cents}, ...] from DashboardController::salesSeries().
         */
        Alpine.data('dashboardSparkline', function (series) {
            return {
                series: series || [],
                width: 640,
                height: 160,
                linePoints: '',
                areaPoints: '',
                firstLabel: '',
                lastLabel: '',
                points: [],   // {xPct, yPct, cents, date} for hover
                hover: -1,

                init: function () {
                    var pts = this.series;
                    if (! pts.length) {
                        return;
                    }

                    var values = pts.map(function (p) { return p.cents; });
                    var max = Math.max.apply(null, values.concat([1]));
                    var min = Math.min.apply(null, values.concat([0]));
                    var range = (max - min) || 1;
                    var pad = 6;
                    var stepX = pts.length > 1 ? this.width / (pts.length - 1) : 0;
                    var self = this;

                    var coords = pts.map(function (p, i) {
                        var x = stepX * i;
                        var y = self.height - pad - (((p.cents - min) / range) * (self.height - pad * 2));
                        self.points.push({
                            xPct: (x / self.width) * 100,
                            yPct: (y / self.height) * 100,
                            cents: p.cents,
                            date: p.date,
                        });
                        return x.toFixed(1) + ',' + y.toFixed(1);
                    });

                    this.linePoints = coords.join(' ');
                    this.areaPoints = '0,' + this.height + ' ' + coords.join(' ') + ' ' + this.width + ',' + this.height;
                    this.firstLabel = pts[0].date;
                    this.lastLabel = pts[pts.length - 1].date;
                },

                // Nearest point to the cursor, so the whole chart width is hoverable.
                onMove: function (event) {
                    if (! this.points.length) { return; }
                    var rect = this.$refs.plot.getBoundingClientRect();
                    var rel = (event.clientX - rect.left) / rect.width;
                    var idx = Math.round(rel * (this.points.length - 1));
                    this.hover = Math.max(0, Math.min(this.points.length - 1, idx));
                },

                onLeave: function () { this.hover = -1; },

                money: function (cents) {
                    return '$' + (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            };
        });

        /** Client-side preview of a picked file, before it is uploaded. */
        Alpine.data('imagePreview', function () {
            return {
                preview: null,

                onChange: function (event) {
                    var file = event.target.files && event.target.files[0];
                    if (! file) {
                        this.preview = null;
                        return;
                    }
                    var reader = new FileReader();
                    var self = this;
                    reader.onload = function (ev) { self.preview = ev.target.result; };
                    reader.readAsDataURL(file);
                },
            };
        });

        /**
         * The collapsed SEO panel on the product and collection editors.
         *
         * Mirrors App\Services\SeoService exactly: an empty field falls back to
         * the generated value, so the preview shows what will actually be
         * rendered rather than an empty box. The counters go amber outside the
         * SERP truncation window and rose when the text will be cut.
         */
        Alpine.data('seoPanel', function (config) {
            var limits = (config && config.limits) || {};

            return {
                open: false,
                title: (config && config.title) || '',
                description: (config && config.description) || '',
                autoTitle: (config && config.autoTitle) || '',
                autoDescription: (config && config.autoDescription) || '',

                get previewTitle() {
                    return this.title.trim() || this.autoTitle;
                },

                get previewDescription() {
                    var text = this.description.trim() || this.autoDescription;
                    var max = limits.descriptionMax || 160;

                    return text.length > max ? text.slice(0, max).trimEnd() + '…' : text;
                },

                get titleTone() {
                    return tone(this.previewTitle.length, limits.titleMin, limits.titleMax);
                },

                get descriptionTone() {
                    var raw = this.description.trim() || this.autoDescription;
                    return tone(raw.length, limits.descriptionMin, limits.descriptionMax);
                },
            };
        });

        /**
         * The Blade editor behind Appearance -> Templates.
         *
         * Hand-rolled on purpose. A code editor library (CodeMirror, Monaco,
         * Ace) would mean either a CDN dependency or a build step, and ShopMGR
         * ships with neither: it installs on a merchant's own server and has to
         * work air-gapped. So this is a plain <textarea> with two siblings kept
         * in lockstep with it - a line-number gutter and a syntax-highlight
         * layer underneath.
         *
         * The alignment contract: the textarea and the highlight layer MUST
         * share font, size, line-height, padding, border width and
         * white-space:pre. Break any one of those and the coloured text drifts
         * away from the caret. See .tpl-editor in the edit view.
         */
        Alpine.data('templateEditor', function () {
            return {
                source: '',
                original: '',
                dirty: false,
                line: 1,
                column: 1,

                init: function () {
                    // Read the template out of the textarea rather than passing
                    // it through an Alpine attribute: x-model would blank a
                    // server-rendered value on init, and a 400-line template
                    // does not belong in an HTML attribute.
                    this.source = this.$refs.input.value;
                    this.original = this.source;
                    this.sync();
                    // The highlight layer scrolls with the text, not with the page.
                    var self = this;
                    this.$refs.input.addEventListener('scroll', function () {
                        self.$refs.highlight.scrollTop = self.$refs.input.scrollTop;
                        self.$refs.highlight.scrollLeft = self.$refs.input.scrollLeft;
                        self.$refs.gutter.scrollTop = self.$refs.input.scrollTop;
                    });
                },

                sync: function () {
                    this.dirty = this.source !== this.original;
                    this.$refs.highlight.innerHTML = highlightBlade(this.source);
                    this.$refs.gutter.innerHTML = gutterMarkup(this.source);
                },

                onInput: function () {
                    this.source = this.$refs.input.value;
                    this.sync();
                    this.caret();
                },

                /** Current caret position, shown in the status bar. */
                caret: function () {
                    var el = this.$refs.input;
                    var upto = el.value.slice(0, el.selectionStart).split('\n');
                    this.line = upto.length;
                    this.column = upto[upto.length - 1].length + 1;
                },

                /**
                 * Tab indents instead of leaving the field. Shift+Tab outdents.
                 * Without this, indenting a Blade block is impossible with the
                 * keyboard alone.
                 */
                onKeydown: function (event) {
                    if (event.key !== 'Tab') {
                        return;
                    }

                    event.preventDefault();

                    var el = this.$refs.input;
                    var start = el.selectionStart;
                    var end = el.selectionEnd;
                    var value = el.value;

                    if (event.shiftKey) {
                        var lineStart = value.lastIndexOf('\n', start - 1) + 1;
                        if (value.slice(lineStart, lineStart + 4) === '    ') {
                            el.value = value.slice(0, lineStart) + value.slice(lineStart + 4);
                            el.selectionStart = el.selectionEnd = Math.max(lineStart, start - 4);
                        }
                    } else {
                        el.value = value.slice(0, start) + '    ' + value.slice(end);
                        el.selectionStart = el.selectionEnd = start + 4;
                    }

                    this.source = el.value;
                    this.sync();
                    this.caret();
                },

                get lineCount() {
                    return this.source.split('\n').length;
                },
            };
        });

        /**
         * Live theme preview on the theme editor.
         *
         * Mirrors App\Services\ThemeService's formulas in the browser so the
         * merchant sees the ramp, radius, spacing and type scale react as they
         * drag a slider, without a round trip and without saving anything.
         */
        Alpine.data('themeForm', function (config) {
            return {
                t: Object.assign({
                    accent: '#e11d48',
                    chrome: '#17132a',
                    chrome_soft: '#221b3d',
                    shop_bg: '#fbfaf9',
                    shop_ink: '#1c1917',
                    shop_muted: '#78716c',
                    shop_line: '#e7e5e4',
                    font_family: 'instrument',
                    font_scale: 100,
                    radius: 100,
                    spacing: 100
                }, (config && config.tokens) || {}),

                deriveRamp: (config && config.deriveRamp) || false,
                hasCustomRamp: (config && config.hasCustomRamp) || false,
                logoPreview: (config && config.logo) || null,
                faviconPreview: (config && config.favicon) || null,
                removeLogo: false,
                removeFavicon: false,

                /** color-mix ramp, identical to the server-side formula. */
                ramp: function (step) {
                    var mix = {
                        50: ['white', 92], 100: ['white', 85], 200: ['white', 72],
                        300: ['white', 55], 400: ['white', 30], 500: [null, 0],
                        600: ['black', 12], 700: ['black', 25], 800: ['black', 40],
                        900: ['black', 52], 950: ['black', 68]
                    }[step];

                    if (! mix || mix[0] === null) {
                        return this.t.accent;
                    }

                    return 'color-mix(in srgb, ' + this.t.accent + ', ' + mix[0] + ' ' + mix[1] + '%)';
                },

                fontStack: function () {
                    return {
                        instrument: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
                        system: "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
                        serif: "ui-serif, Georgia, Cambria, 'Times New Roman', serif",
                        mono: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
                    }[this.t.font_family] || 'ui-sans-serif, system-ui, sans-serif';
                },

                /** The preview pane's inline custom properties. */
                previewStyle: function () {
                    var r = (parseInt(this.t.radius, 10) || 0) / 100;
                    var s = (parseInt(this.t.spacing, 10) || 100) / 100;

                    return [
                        '--p-accent:' + this.t.accent,
                        '--p-accent-soft:' + this.ramp(50),
                        '--p-accent-line:' + this.ramp(200),
                        '--p-accent-dark:' + this.ramp(700),
                        '--p-chrome:' + this.t.chrome,
                        '--p-bg:' + this.t.shop_bg,
                        '--p-ink:' + this.t.shop_ink,
                        '--p-muted:' + this.t.shop_muted,
                        '--p-line:' + this.t.shop_line,
                        '--p-radius:' + (0.75 * r) + 'rem',
                        '--p-radius-sm:' + (0.375 * r) + 'rem',
                        '--p-gap:' + (s) + 'rem',
                        '--p-font:' + this.fontStack(),
                        'font-size:' + (parseInt(this.t.font_scale, 10) || 100) + '%'
                    ].join(';');
                },

                onFile: function (event, key) {
                    var file = event.target.files && event.target.files[0];
                    if (! file) {
                        return;
                    }
                    var reader = new FileReader();
                    var self = this;
                    reader.onload = function (ev) {
                        if (key === 'logo') { self.logoPreview = ev.target.result; }
                        else { self.faviconPreview = ev.target.result; }
                    };
                    reader.readAsDataURL(file);
                },
            };
        });
    });

    /* ------------------------------------------------------------------ *
     * Blade syntax highlighting, hand-rolled.
     *
     * One pass, one regex alternation, five token classes. It runs over the RAW
     * source and HTML-escapes each slice as it emits it, rather than escaping
     * first - escaping first would turn every "<" into "&lt;" and there would
     * be no tags left to recognise.
     * ------------------------------------------------------------------ */

    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function highlightBlade(source) {
        var pattern = /(\{\{--[\s\S]*?--\}\})|(\{!![\s\S]*?!!\}|\{\{[\s\S]*?\}\})|(@[a-zA-Z]+)|(<!--[\s\S]*?-->)|(<\/?[a-zA-Z][^>]*>)/g;
        var out = '';
        var last = 0;
        var match;

        while ((match = pattern.exec(source)) !== null) {
            out += escapeHtml(source.slice(last, match.index));

            var cls = match[1] ? 'tk-comment'
                : match[2] ? 'tk-echo'
                : match[3] ? 'tk-directive'
                : match[4] ? 'tk-comment'
                : 'tk-tag';

            out += '<span class="' + cls + '">' + escapeHtml(match[0]) + '</span>';
            last = match.index + match[0].length;
        }

        out += escapeHtml(source.slice(last));

        // Trailing newline keeps the highlight layer exactly as tall as the
        // textarea when the file ends with one.
        return out + '\n';
    }

    function gutterMarkup(source) {
        var count = source.split('\n').length;
        var rows = '';

        for (var n = 1; n <= count; n++) {
            rows += n + '\n';
        }

        return escapeHtml(rows);
    }

    /**
     * Counter colour for a length against its SERP window: rose past the
     * truncation point, amber below the useful minimum, slate when it lands.
     */
    function tone(length, min, max) {
        if (length > (max || 0)) {
            return 'text-rose-600';
        }
        if (length < (min || 0)) {
            return 'text-amber-600';
        }

        return 'text-slate-500';
    }

    function blankVariant() {
        return {
            id: null,
            option1_name: '', option1_value: '',
            option2_name: '', option2_value: '',
            option3_name: '', option3_value: '',
            sku: '', barcode: '',
            price: '', compare_at_price: '', cost: '',
            inventory_qty: 0, weight_grams: 0,
            track_inventory: true,
        };
    }

    /* ------------------------------------------------------------------ *
     * Slug auto-fill: any input tagged [data-slug-source="#target"] fills the
     * target slug field as the user types, until the target has been edited
     * by hand — used on the product and collection forms.
     * ------------------------------------------------------------------ */
    function slugify(value) {
        return value
            .toString()
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-slug-source]').forEach(function (source) {
            var target = document.querySelector(source.getAttribute('data-slug-source'));
            if (! target) {
                return;
            }

            var edited = target.value.trim().length > 0;
            target.addEventListener('input', function () { edited = true; });
            source.addEventListener('input', function () {
                if (! edited) {
                    target.value = slugify(source.value);
                }
            });
        });
    });
})();
