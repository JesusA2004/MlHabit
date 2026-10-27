/**
 * Utilidades de Openpay.js (tokenización en el navegador). Los datos de la
 * tarjeta van directo del navegador a Openpay; a nuestro servidor solo llega
 * el token de un solo uso y el device_session_id.
 *
 * Scripts oficiales (docs/pagos/OPENPAY-INVESTIGACION.md §2). No hay hash
 * SRI publicado, así que se cargan una sola vez desde js.openpay.mx.
 */

export type OpenpayClientConfig = {
    merchantId: string;
    publicKey: string;
    sandbox: boolean;
    configured?: boolean;
};

type TokenResponse = { data: { id: string; card?: { brand?: string } } };
type TokenError = {
    status?: number;
    message?: string;
    data?: { error_code?: number; description?: string };
};

type OpenPayGlobal = {
    setId(id: string): void;
    setApiKey(key: string): void;
    setSandboxMode(enabled: boolean): void;
    deviceData: { setup(formId?: string, fieldName?: string): string };
    token: {
        create(
            params: Record<string, string>,
            success: (response: TokenResponse) => void,
            error: (response: TokenError) => void,
        ): void;
    };
    card?: {
        validateCardNumber?(value: string): boolean;
        cardType?(value: string): string;
    };
};

declare global {
    interface Window {
        OpenPay?: OpenPayGlobal;
    }
}

const SCRIPTS = [
    'https://js.openpay.mx/openpay.v1.min.js',
    'https://js.openpay.mx/openpay-data.v1.min.js',
];

let loading: Promise<OpenPayGlobal> | null = null;

function inject(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${src}"]`,
        );

        if (existing?.dataset.loaded === 'true') {
            resolve();

            return;
        }

        const script = existing ?? document.createElement('script');
        script.addEventListener(
            'load',
            () => {
                script.dataset.loaded = 'true';
                resolve();
            },
            { once: true },
        );
        script.addEventListener(
            'error',
            () => {
                script.remove();
                reject(new Error('openpay-script'));
            },
            { once: true },
        );

        if (!existing) {
            script.src = src;
            script.async = false;
            script.referrerPolicy = 'strict-origin-when-cross-origin';
            document.head.appendChild(script);
        }
    });
}

/** Carga openpay.js y luego openpay-data.js (depende del primero). Una sola vez por página. */
export function loadOpenpay(): Promise<OpenPayGlobal> {
    if (window.OpenPay?.deviceData) {
        return Promise.resolve(window.OpenPay);
    }

    if (!loading) {
        loading = (async () => {
            for (const src of SCRIPTS) {
                await inject(src);
            }

            if (!window.OpenPay) {
                throw new Error('openpay-missing');
            }

            return window.OpenPay;
        })().catch((error) => {
            loading = null;
            throw error;
        });
    }

    return loading;
}

/** Configura Openpay (merchant, llave PÚBLICA, sandbox) y devuelve el device_session_id. */
export function setupOpenpay(
    openpay: OpenPayGlobal,
    config: OpenpayClientConfig,
): string {
    openpay.setId(config.merchantId);
    openpay.setApiKey(config.publicKey);
    // Debe ir antes de deviceData.setup() (nota oficial de openpay-data.js).
    openpay.setSandboxMode(config.sandbox);

    return openpay.deviceData.setup();
}

export function createToken(
    openpay: OpenPayGlobal,
    params: Record<string, string>,
): Promise<string> {
    return new Promise((resolve, reject) => {
        openpay.token.create(
            params,
            (response) => resolve(response.data.id),
            (error) => reject(error),
        );
    });
}

/** Mensaje seguro para errores de tokenización (antes de llegar a nuestro servidor). */
export function tokenErrorMessage(error: unknown): string {
    const code = (error as TokenError | undefined)?.data?.error_code;

    switch (code) {
        case 2004:
            return 'El número de tarjeta no es válido. Revísalo e inténtalo de nuevo.';
        case 2005:
            return 'La tarjeta está vencida. Intenta con otra tarjeta.';
        case 2006:
        case 2009:
            return 'El código de seguridad (CVV) no es válido.';
        case 2011:
            return 'Ese tipo de tarjeta no se acepta. Intenta con una tarjeta Visa o Mastercard.';
        case 1001:
        case 1003:
            return 'Algún dato de la tarjeta no es válido. Revísalo e inténtalo de nuevo.';
        default:
            return 'No pudimos validar la tarjeta. Revisa los datos o intenta con otra tarjeta. No se realizó ningún cargo.';
    }
}

export type CardBrand = 'visa' | 'mastercard' | 'amex' | 'carnet' | null;

export function detectBrand(digits: string): CardBrand {
    if (digits.startsWith('4')) return 'visa';
    if (/^3[47]/.test(digits)) return 'amex';
    if (/^(506[2-4]|5895|6275|6363|6393)/.test(digits)) return 'carnet';
    if (/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/.test(digits))
        return 'mastercard';

    return null;
}

export const BRAND_LABEL: Record<Exclude<CardBrand, null>, string> = {
    visa: 'Visa',
    mastercard: 'Mastercard',
    amex: 'American Express',
    carnet: 'Carnet',
};

/** Agrupa dígitos: 4-4-4-4(-3) o 4-6-5 para American Express. */
export function formatCardNumber(digits: string, brand: CardBrand): string {
    const groups = brand === 'amex' ? [4, 6, 5] : [4, 4, 4, 4, 3];
    const out: string[] = [];
    let index = 0;

    for (const size of groups) {
        if (index >= digits.length) break;
        out.push(digits.slice(index, index + size));
        index += size;
    }

    return out.join(' ');
}

export function luhnValid(digits: string): boolean {
    if (!/^\d{13,19}$/.test(digits)) return false;

    let sum = 0;
    let double = false;

    for (let i = digits.length - 1; i >= 0; i--) {
        let n = Number(digits[i]);

        if (double) {
            n *= 2;
            if (n > 9) n -= 9;
        }

        sum += n;
        double = !double;
    }

    return sum % 10 === 0;
}

export function uuid(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join(
        '',
    );

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
