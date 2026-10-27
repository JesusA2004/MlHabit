/*
 * STUB DE PRUEBAS E2E de openpay.v1.min.js — NO es el SDK de Openpay.
 * Playwright lo sirve en lugar de https://js.openpay.mx/openpay.v1.min.js
 * (page.route) porque en este entorno no hay credenciales de sandbox.
 * Devuelve un token determinista a partir del número de tarjeta
 * (tok_e2e_<últimos 4>) que App\Payments\Testing\E2eSandboxGateway entiende.
 * Valida como Openpay: número Luhn (2004), CVV (2006) y vencimiento (2005).
 */
(function () {
    window.__openpayStubCalls = { tokens: 0, setup: 0 };

    function luhn(digits) {
        var sum = 0;
        var dbl = false;
        for (var i = digits.length - 1; i >= 0; i--) {
            var n = Number(digits[i]);
            if (dbl) {
                n *= 2;
                if (n > 9) n -= 9;
            }
            sum += n;
            dbl = !dbl;
        }
        return digits.length >= 13 && sum % 10 === 0;
    }

    window.OpenPay = {
        _id: null,
        _key: null,
        _sandbox: null,
        setId: function (id) {
            this._id = id;
        },
        setApiKey: function (key) {
            this._key = key;
        },
        setSandboxMode: function (on) {
            this._sandbox = on;
        },
        getSandboxMode: function () {
            return this._sandbox;
        },
        deviceData: {
            setup: function () {
                window.__openpayStubCalls.setup++;
                return 'e2edevicesession00000000000000000';
            },
        },
        token: {
            create: function (params, success, error) {
                window.__openpayStubCalls.tokens++;
                var digits = String(params.card_number || '').replace(
                    /\D/g,
                    '',
                );
                setTimeout(function () {
                    if (!luhn(digits)) {
                        error({
                            status: 400,
                            data: {
                                error_code: 2004,
                                description: 'invalid card',
                            },
                        });
                        return;
                    }
                    if (!/^\d{3,4}$/.test(String(params.cvv2 || ''))) {
                        error({
                            status: 400,
                            data: {
                                error_code: 2006,
                                description: 'cvv required',
                            },
                        });
                        return;
                    }
                    success({
                        data: {
                            id: 'tok_e2e_' + digits.slice(-4),
                            card: { brand: 'visa' },
                        },
                    });
                }, 50);
            },
        },
    };
})();
