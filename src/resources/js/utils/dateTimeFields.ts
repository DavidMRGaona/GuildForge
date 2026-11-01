/**
 * Reading and writing calendar days ('YYYY-MM-DD') and times of day ('HH:MM') typed by
 * people, for FormDatePicker and FormTimePicker. Days carry no time zone: the arithmetic
 * runs in UTC so the browser's zone never moves a day.
 */

export type DatePart = 'day' | 'month' | 'year';

export interface DateFieldPattern {
    order: DatePart[];
    separator: string;
}

const DAY = /^(\d{4})-(\d{2})-(\d{2})$/;
const MONTH = /^(\d{4})-(\d{2})$/;

/** The order of day, month and year and the separator the locale writes numeric days with. */
export function dateFieldPattern(locale: string): DateFieldPattern {
    const parts = new Intl.DateTimeFormat(locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: 'UTC',
    }).formatToParts(new Date(Date.UTC(2026, 10, 22)));
    const order = parts
        .map((part) => part.type)
        .filter((type): type is DatePart => type === 'day' || type === 'month' || type === 'year');
    const separator = parts.find((part) => part.type === 'literal')?.value.trim() || '/';

    return order.length === 3
        ? { order, separator }
        : { order: ['day', 'month', 'year'], separator };
}

/** A day as the locale writes it ('07/03/2026' in Spanish); anything else as it came. */
export function formatDateField(value: string, locale: string): string {
    const match = DAY.exec(value);
    if (match === null) {
        return value;
    }

    const parts: Record<DatePart, string> = { year: match[1]!, month: match[2]!, day: match[3]! };
    const pattern = dateFieldPattern(locale);

    return pattern.order.map((part) => parts[part]).join(pattern.separator);
}

/**
 * The day typed in the locale's order ('7/3/26', '07-03-2026', '7.3.2026') or as
 * 'YYYY-MM-DD', or null when it is not a day of the calendar.
 */
export function parseDateField(text: string, locale: string): string | null {
    const trimmed = text.trim();
    const iso = DAY.exec(trimmed);
    if (iso !== null) {
        return validDay(Number(iso[1]), Number(iso[2]), Number(iso[3]));
    }

    const numbers = trimmed.split(/[\s/.-]+/);
    if (numbers.length !== 3 || numbers.some((piece) => !/^\d{1,4}$/.test(piece))) {
        return null;
    }

    const values: Partial<Record<DatePart, number>> = {};
    dateFieldPattern(locale).order.forEach((part, index) => {
        values[part] = Number(numbers[index]);
    });
    const year = values.year ?? 0;

    return validDay(year < 100 ? 2000 + year : year, values.month ?? 0, values.day ?? 0);
}

/** A time typed as '18:30', '9.30', '9h30', '9h', '9' or '0930', as 'HH:MM', or null. */
export function parseTimeField(text: string): string | null {
    const trimmed = text.trim().toLowerCase();
    const separated = /^(\d{1,2})\s*[:.h]\s*(\d{2})?$/.exec(trimmed);
    const packed = /^(\d{1,4})$/.exec(trimmed);
    let hours: number;
    let minutes: number;
    if (separated !== null) {
        hours = Number(separated[1]);
        minutes = Number(separated[2] ?? 0);
    } else if (packed !== null) {
        const digits = packed[1]!;
        hours = Number(digits.length <= 2 ? digits : digits.slice(0, -2));
        minutes = Number(digits.length <= 2 ? 0 : digits.slice(-2));
    } else {
        return null;
    }

    if (hours > 23 || minutes > 59) {
        return null;
    }

    return `${pad(hours)}:${pad(minutes)}`;
}

/** Every time of the day in steps of the given minutes; whole hours when it does not fit. */
export function timeOptions(stepMinutes: number): string[] {
    const step =
        Number.isInteger(stepMinutes) && stepMinutes > 0 && stepMinutes <= 720 ? stepMinutes : 60;
    const options: string[] = [];
    for (let minute = 0; minute < 24 * 60; minute += step) {
        options.push(`${pad(Math.floor(minute / 60))}:${pad(minute % 60)}`);
    }

    return options;
}

/**
 * The weeks that cover a month ('YYYY-MM'), each with its seven days as 'YYYY-MM-DD',
 * starting on the given weekday (0 Sunday … 6 Saturday). The first and last weeks carry
 * days of the neighbouring months.
 */
export function monthWeeks(month: string, firstWeekday: number): string[][] {
    const match = MONTH.exec(month);
    if (match === null) {
        return [];
    }

    const year = Number(match[1]);
    const index = Number(match[2]) - 1;
    const first = Date.UTC(year, index, 1);
    const last = Date.UTC(year, index + 1, 0);
    const lead = (new Date(first).getUTCDay() - firstWeekday + 7) % 7;
    const weeks: string[][] = [];
    for (let start = first - lead * 86_400_000; start <= last; start += 7 * 86_400_000) {
        weeks.push(Array.from({ length: 7 }, (_, offset) => dayKey(start + offset * 86_400_000)));
    }

    return weeks;
}

/** The month ('YYYY-MM') a number of months away. */
export function addMonths(month: string, months: number): string {
    const match = MONTH.exec(month);
    if (match === null) {
        return month;
    }

    return dayKey(Date.UTC(Number(match[1]), Number(match[2]) - 1 + months, 1)).slice(0, 7);
}

/** The day ('YYYY-MM-DD') a number of days away. */
export function addDays(day: string, days: number): string {
    return dayKey(Date.parse(`${day}T00:00:00Z`) + days * 86_400_000);
}

/** Weekday of a day ('YYYY-MM-DD'), 0 Sunday … 6 Saturday. */
export function weekdayOf(day: string): number {
    return new Date(Date.parse(`${day}T00:00:00Z`)).getUTCDay();
}

function validDay(year: number, month: number, day: number): string | null {
    if (year < 1 || month < 1 || month > 12 || day < 1) {
        return null;
    }

    const key = `${String(year).padStart(4, '0')}-${pad(month)}-${pad(day)}`;

    return dayKey(Date.UTC(year, month - 1, day)) === key ? key : null;
}

function dayKey(time: number): string {
    return new Date(time).toISOString().slice(0, 10);
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}
