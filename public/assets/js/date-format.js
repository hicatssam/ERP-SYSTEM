/* ═══════════════════════════════════════════════
   DAHAB — Global Arabic date/time presentation
   ═══════════════════════════════════════════════
   Display-only transformation. Database values, form controls and API payloads
   remain untouched.
*/
(() => {
    const DAYS = [
        'الأحد',
        'الاثنين',
        'الثلاثاء',
        'الأربعاء',
        'الخميس',
        'الجمعة',
        'السبت',
    ];

    const MONTHS = [
        '',
        'يناير',
        'فبراير',
        'مارس',
        'أبريل',
        'مايو',
        'يونيو',
        'يوليو',
        'أغسطس',
        'سبتمبر',
        'أكتوبر',
        'نوفمبر',
        'ديسمبر',
    ];

    const SKIP_TAGS = new Set([
        'SCRIPT',
        'STYLE',
        'TEXTAREA',
        'INPUT',
        'SELECT',
        'OPTION',
        'PRE',
        'CODE',
        'NOSCRIPT',
    ]);

    const timezone =
        document
            .querySelector('meta[name="app-timezone"]')
            ?.getAttribute('content')
        || 'Asia/Jerusalem';

    const pad = value => String(value).padStart(2, '0');

    const todayParts = () => {
        try {
            const parts = new Intl.DateTimeFormat(
                'en-CA',
                {
                    timeZone: timezone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                }
            ).formatToParts(new Date());

            const values = Object.fromEntries(
                parts.map(part => [
                    part.type,
                    part.value,
                ])
            );

            return {
                year: Number(values.year),
                month: Number(values.month),
                day: Number(values.day),
            };
        } catch (_) {
            const now = new Date();

            return {
                year: now.getFullYear(),
                month: now.getMonth() + 1,
                day: now.getDate(),
            };
        }
    };

    const calendarStamp = ({ year, month, day }) =>
        Date.UTC(year, month - 1, day);

    const dayDifference = parts => {
        const today = todayParts();

        return Math.round(
            (
                calendarStamp(parts)
                - calendarStamp(today)
            )
            / 86400000
        );
    };

    const weekday = parts =>
        DAYS[
            new Date(
                Date.UTC(
                    parts.year,
                    parts.month - 1,
                    parts.day
                )
            ).getUTCDay()
        ];

    const dayHeading = parts => {
        const diff = dayDifference(parts);
        const name = weekday(parts);

        if (diff === 0) {
            return `اليوم، ${name}`;
        }

        if (diff === 1) {
            return `غدًا، ${name}`;
        }

        if (diff === -1) {
            return `أمس، ${name}`;
        }

        return name;
    };

    const timeText = (hour, minute) => {
        const numericHour = Number(hour);
        const hour12 = numericHour % 12 || 12;

        return `${hour12}:${pad(minute)} ${numericHour < 12 ? 'صباحًا' : 'مساءً'}`;
    };

    const dateText = parts => {
        const heading = dayHeading(parts);
        const diff = dayDifference(parts);
        const fullDate =
            `${parts.day} ${MONTHS[parts.month]} ${parts.year}`;

        if (Math.abs(diff) <= 1) {
            return `${heading} · ${fullDate}`;
        }

        return `${heading}، ${fullDate}`;
    };

    const dateTimeText = parts => {
        const heading = dayHeading(parts);
        const clock = timeText(
            parts.hour,
            parts.minute
        );
        const diff = dayDifference(parts);

        if (Math.abs(diff) <= 1) {
            return `${heading} · ${clock}`;
        }

        return `${heading}، ${parts.day} ${MONTHS[parts.month]} ${parts.year} · ${clock}`;
    };

    const validParts = parts => {
        if (
            !Number.isInteger(parts.year)
            || !Number.isInteger(parts.month)
            || !Number.isInteger(parts.day)
            || parts.month < 1
            || parts.month > 12
            || parts.day < 1
            || parts.day > 31
        ) {
            return false;
        }

        if (parts.hour !== undefined) {
            if (
                parts.hour < 0
                || parts.hour > 23
                || parts.minute < 0
                || parts.minute > 59
            ) {
                return false;
            }
        }

        const test = new Date(
            Date.UTC(
                parts.year,
                parts.month - 1,
                parts.day
            )
        );

        return test.getUTCFullYear() === parts.year
            && test.getUTCMonth() + 1 === parts.month
            && test.getUTCDate() === parts.day;
    };

    const dateTimePartsInSystemTimezone = value => {
        try {
            const parsed = new Date(value);

            if (Number.isNaN(parsed.getTime())) {
                return null;
            }

            const formatter = new Intl.DateTimeFormat(
                'en-CA',
                {
                    timeZone: timezone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    hourCycle: 'h23',
                }
            );

            const values = Object.fromEntries(
                formatter
                    .formatToParts(parsed)
                    .map(part => [
                        part.type,
                        part.value,
                    ])
            );

            const parts = {
                year: Number(values.year),
                month: Number(values.month),
                day: Number(values.day),
                hour: Number(values.hour),
                minute: Number(values.minute),
            };

            return validParts(parts)
                ? parts
                : null;
        } catch (_) {
            return null;
        }
    };

    const formatZonedIsoDates = text => text.replace(
        /(?<![\p{L}\p{N}_-])(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:\.\d{1,6})?(?:Z|[+-]\d{2}:?\d{2}))(?![\p{L}\p{N}_-])/gu,
        original => {
            const parts =
                dateTimePartsInSystemTimezone(
                    original
                );

            return parts
                ? dateTimeText(parts)
                : original;
        }
    );

    const formatIsoDates = text => text.replace(
        /(?<![\p{L}\p{N}_-])(\d{4})[-\/](\d{2})[-\/](\d{2})(?:(?:T|\s+(?:·|—|-)?\s*)(\d{1,2}):(\d{2})(?::\d{2})?)?(?![\p{L}\p{N}_-])/gu,
        (
            original,
            year,
            month,
            day,
            hour,
            minute
        ) => {
            const parts = {
                year: Number(year),
                month: Number(month),
                day: Number(day),
            };

            if (hour !== undefined) {
                parts.hour = Number(hour);
                parts.minute = Number(minute);
            }

            if (!validParts(parts)) {
                return original;
            }

            return hour === undefined
                ? dateText(parts)
                : dateTimeText(parts);
        }
    );

    const formatSlashDates = text => text.replace(
        /(?<![\p{L}\p{N}_-])(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::\d{2})?)?(?![\p{L}\p{N}_-])/gu,
        (
            original,
            day,
            month,
            year,
            hour,
            minute
        ) => {
            const parts = {
                year: Number(year),
                month: Number(month),
                day: Number(day),
            };

            if (hour !== undefined) {
                parts.hour = Number(hour);
                parts.minute = Number(minute);
            }

            if (!validParts(parts)) {
                return original;
            }

            return hour === undefined
                ? dateText(parts)
                : dateTimeText(parts);
        }
    );

    const formatStandaloneTime = text => {
        const match = text.match(
            /^(\s*)(?:·\s*)?(\d{1,2}):(\d{2})(?::\d{2})?(\s*)$/
        );

        if (!match) {
            return text;
        }

        const hour = Number(match[2]);
        const minute = Number(match[3]);

        if (
            hour < 0
            || hour > 23
            || minute < 0
            || minute > 59
        ) {
            return text;
        }

        return `${match[1]}${timeText(hour, minute)}${match[4]}`;
    };

    const formatText = text => {
        let output = formatZonedIsoDates(text);
        output = formatIsoDates(output);
        output = formatSlashDates(output);
        output = formatStandaloneTime(output);

        return output;
    };

    const shouldSkip = node => {
        const parent = node.parentElement;

        if (!parent) {
            return true;
        }

        if (SKIP_TAGS.has(parent.tagName)) {
            return true;
        }

        return Boolean(
            parent.closest(
                '[data-date-format="off"],[data-dahab-date="off"]'
            )
        );
    };

    const formatTextNode = node => {
        if (
            node.nodeType !== Node.TEXT_NODE
            || shouldSkip(node)
        ) {
            return;
        }

        const original = node.nodeValue || '';

        if (
            !/\d{1,4}[-\/:]\d{1,2}/.test(
                original
            )
        ) {
            return;
        }

        const formatted = formatText(original);

        if (formatted !== original) {
            node.nodeValue = formatted;
        }
    };

    const formatTree = root => {
        if (!root) {
            return;
        }

        if (root.nodeType === Node.TEXT_NODE) {
            formatTextNode(root);
            return;
        }

        if (
            root.nodeType !== Node.ELEMENT_NODE
            && root.nodeType !== Node.DOCUMENT_NODE
            && root.nodeType !== Node.DOCUMENT_FRAGMENT_NODE
        ) {
            return;
        }

        if (
            root.nodeType === Node.ELEMENT_NODE
            && (
                SKIP_TAGS.has(root.tagName)
                || root.matches(
                    '[data-date-format="off"],[data-dahab-date="off"]'
                )
            )
        ) {
            return;
        }

        const walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_TEXT
        );

        let current;

        while ((current = walker.nextNode())) {
            formatTextNode(current);
        }
    };

    let observerBusy = false;

    const observer = new MutationObserver(
        mutations => {
            if (observerBusy) {
                return;
            }

            observerBusy = true;

            try {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(
                        node => formatTree(node)
                    );

                    if (
                        mutation.type === 'characterData'
                        && mutation.target
                    ) {
                        formatTextNode(
                            mutation.target
                        );
                    }
                });
            } finally {
                observerBusy = false;
            }
        }
    );

    const start = () => {
        formatTree(document.body);

        observer.observe(
            document.body,
            {
                childList: true,
                subtree: true,
                characterData: true,
            }
        );
    };

    window.DahabDateFormatter = {
        formatText,
        formatTree,
        dateText,
        dateTimeText,
        timeText,
        timezone,
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            start,
            { once: true }
        );
    } else {
        start();
    }
})();
