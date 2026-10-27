<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Importe monetario inmutable con precisión exacta (bcmath, 2 decimales).
 * Nunca usar float para dinero: los valores entran y salen como string
 * decimal ("113500.00"), igual que las columnas DECIMAL(14,2).
 */
final class Money implements JsonSerializable, Stringable
{
    /** Fijo: DECIMAL(14,2). round() asume esta escala. */
    private const SCALE = 2;

    /** @param numeric-string $amount */
    private function __construct(public readonly string $amount) {}

    public static function of(string|int|self|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $value = trim((string) ($value ?? '0'));
        if ($value === '') {
            $value = '0';
        }

        if (! is_numeric($value) || ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Importe inválido: {$value}");
        }

        return new self(self::round($value, self::SCALE));
    }

    public static function zero(): self
    {
        return new self('0.00');
    }

    public function plus(string|int|self $other): self
    {
        return new self(bcadd($this->amount, self::of($other)->amount, self::SCALE));
    }

    public function minus(string|int|self $other): self
    {
        return new self(bcsub($this->amount, self::of($other)->amount, self::SCALE));
    }

    /** Multiplica por una cantidad decimal (p. ej. 1.500 piezas) con redondeo half-up. */
    public function times(string|int $factor): self
    {
        $factor = self::numeric($factor);

        return new self(self::round(bcmul($this->amount, $factor, 8), self::SCALE));
    }

    /** Porcentaje: Money::of('113500')->percent('10') → 11350.00 */
    public function percent(string|int $percent): self
    {
        $percent = self::numeric($percent);

        return new self(self::round(bcdiv(bcmul($this->amount, $percent, 8), '100', 8), self::SCALE));
    }

    public function min(string|int|self $other): self
    {
        $other = self::of($other);

        return $this->compare($other) <= 0 ? $this : $other;
    }

    public function max(string|int|self $other): self
    {
        $other = self::of($other);

        return $this->compare($other) >= 0 ? $this : $other;
    }

    public function compare(string|int|self $other): int
    {
        return bccomp($this->amount, self::of($other)->amount, self::SCALE);
    }

    public function equals(string|int|self $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function isZero(): bool
    {
        return $this->compare('0') === 0;
    }

    public function isPositive(): bool
    {
        return $this->compare('0') > 0;
    }

    public function isNegative(): bool
    {
        return $this->compare('0') < 0;
    }

    /** Centavos enteros (para pasarelas que los requieran). */
    public function cents(): int
    {
        return (int) bcmul($this->amount, '100', 0);
    }

    public function format(string $currency = 'MXN'): string
    {
        $negative = str_starts_with($this->amount, '-');
        [$int, $dec] = explode('.', ltrim($this->amount, '-'));
        $int = number_format((int) $int, 0, '.', ',');

        return ($negative ? '-' : '').'$'.$int.'.'.$dec.($currency !== '' ? " {$currency}" : '');
    }

    public function __toString(): string
    {
        return $this->amount;
    }

    public function jsonSerialize(): string
    {
        return $this->amount;
    }

    /** @return numeric-string */
    private static function numeric(string|int $value): string
    {
        $value = (string) $value;
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Número inválido: {$value}");
        }

        return $value;
    }

    /**
     * Redondeo half-up a 2 decimales (bcmath trunca, por eso se suma ±0.005).
     *
     * @param  numeric-string  $value
     * @return numeric-string
     */
    private static function round(string $value, int $scale): string
    {
        $rounded = str_starts_with($value, '-')
            ? bcsub($value, '0.005', $scale)
            : bcadd($value, '0.005', $scale);

        return $rounded === '-0.00' ? '0.00' : $rounded;
    }
}
