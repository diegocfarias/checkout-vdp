import fs from 'node:fs';
import path from 'node:path';

let envLoaded = false;

export function loadE2eEnv(filePath = path.resolve(process.cwd(), '.env.e2e')): void {
    if (envLoaded) return;
    envLoaded = true;

    if (!fs.existsSync(filePath)) return;

    const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);
    for (const rawLine of lines) {
        const line = rawLine.trim();
        if (!line || line.startsWith('#')) continue;

        const match = line.match(/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/);
        if (!match) continue;

        const key = match[1];
        let value = match[2].trim();
        if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
            value = value.slice(1, -1);
        }

        if (process.env[key] === undefined) {
            process.env[key] = value;
        }
    }
}

loadE2eEnv();

export function env(name: string, fallback = ''): string {
    return process.env[name] ?? fallback;
}

export function isFlagEnabled(name: string): boolean {
    return ['1', 'true', 'yes', 'on'].includes(env(name).trim().toLowerCase());
}

export function getE2eBaseUrl(): string {
    const port = env('E2E_PORT', '8000');
    return env('E2E_BASE_URL', `http://127.0.0.1:${port}`).replace(/\/+$/, '');
}

export function formatDate(date: Date): string {
    return date.toISOString().slice(0, 10);
}

export function futureDate(daysAhead: number): string {
    const date = new Date();
    date.setDate(date.getDate() + daysAhead);
    return formatDate(date);
}

export type SearchParams = {
    trip_type: 'roundtrip' | 'oneway';
    departure: string;
    arrival: string;
    outbound_date: string;
    inbound_date?: string;
    adults: string;
    children: string;
    infants: string;
    cabin: 'EC' | 'EX';
};

export function defaultSearchParams(overrides: Partial<SearchParams> = {}): SearchParams {
    const params: SearchParams = {
        trip_type: 'roundtrip',
        departure: env('E2E_DEFAULT_DEPARTURE', 'RIO').toUpperCase(),
        arrival: env('E2E_DEFAULT_ARRIVAL', 'VIX').toUpperCase(),
        outbound_date: env('E2E_DEFAULT_OUTBOUND_DATE', futureDate(35)),
        inbound_date: env('E2E_DEFAULT_INBOUND_DATE', futureDate(40)),
        adults: env('E2E_DEFAULT_ADULTS', '1'),
        children: env('E2E_DEFAULT_CHILDREN', '0'),
        infants: env('E2E_DEFAULT_INFANTS', '0'),
        cabin: env('E2E_DEFAULT_CABIN', 'EC').toUpperCase() === 'EX' ? 'EX' : 'EC',
    };

    const merged = { ...params, ...overrides };
    if (merged.trip_type === 'oneway') {
        delete merged.inbound_date;
    }

    return merged;
}

export const credentials = {
    adminEmail: env('E2E_ADMIN_EMAIL'),
    adminPassword: env('E2E_ADMIN_PASSWORD'),
    customerEmail: env('E2E_CUSTOMER_EMAIL'),
    customerPassword: env('E2E_CUSTOMER_PASSWORD'),
};

export const fixtures = {
    checkoutToken: env('E2E_CHECKOUT_TOKEN'),
    trackingCode: env('E2E_TRACKING_CODE'),
    trackingDocument: env('E2E_TRACKING_DOCUMENT'),
    supportTicketPath: env('E2E_SUPPORT_TICKET_PATH'),
};

export const flags = {
    enablePurchaseFlow: isFlagEnabled('E2E_ENABLE_PURCHASE_FLOW'),
    submitPayment: isFlagEnabled('E2E_SUBMIT_PAYMENT'),
    allowSupportMutations: isFlagEnabled('E2E_ALLOW_SUPPORT_MUTATIONS'),
};
