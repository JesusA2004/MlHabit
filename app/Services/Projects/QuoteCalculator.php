<?php

namespace App\Services\Projects;

use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Totales de cotización con aritmética exacta (bcmath vía Money). Es la
 * ÚNICA fuente de los importes guardados: el frontend solo muestra una
 * vista previa y el backend recalcula en cada guardado.
 *
 * Por partida:  importe = round(precio × cantidad)  ·  total = importe − descuento
 * Cotización:   subtotal = Σ importes  ·  descuento = Σ descuentos  ·  neto = subtotal − descuento
 *   - Precios con IVA:  total = neto; base = round(neto ÷ (1 + tasa)); IVA = total − base
 *   - Precios sin IVA:  IVA = round(neto × tasa); total = neto + IVA
 * Redondeo half-up a centavos en cada paso documentado.
 */
class QuoteCalculator
{
    /**
     * @param  list<array<string, mixed>>  $items  quantity, unit_price, discount_amount
     * @return array{items: list<array{gross: string, discount_amount: string, line_total: string, quantity: string, unit_price: string}>, subtotal: string, discount_total: string, tax_total: string, total: string}
     */
    public function calculate(array $items, bool $pricesIncludeTax, string $taxRate): array
    {
        $subtotal = Money::zero();
        $discountTotal = Money::zero();
        $lines = [];

        foreach ($items as $index => $item) {
            $quantity = $this->quantity((string) $item['quantity'], $index);
            $unitPrice = Money::of((string) $item['unit_price']);
            $discount = Money::of((string) ($item['discount_amount'] ?? '0'));

            if ($unitPrice->isNegative() || $discount->isNegative()) {
                throw ValidationException::withMessages(["items.{$index}.unit_price" => 'Los importes no pueden ser negativos.']);
            }

            $gross = $unitPrice->times($quantity);
            if ($discount->compare($gross) > 0) {
                throw ValidationException::withMessages(["items.{$index}.discount_amount" => 'El descuento no puede ser mayor que el importe de la partida.']);
            }

            $subtotal = $subtotal->plus($gross);
            $discountTotal = $discountTotal->plus($discount);

            $lines[] = [
                'quantity' => $quantity,
                'unit_price' => $unitPrice->amount,
                'gross' => $gross->amount,
                'discount_amount' => $discount->amount,
                'line_total' => $gross->minus($discount)->amount,
            ];
        }

        $net = $subtotal->minus($discountTotal);
        $rate = $this->rate($taxRate);

        if ($pricesIncludeTax) {
            $total = $net;
            $base = Money::of(bcdiv($net->amount, bcadd('1', $rate, 6), 8));
            $tax = $total->minus($base);
        } else {
            $tax = $net->times($rate);
            $total = $net->plus($tax);
        }

        return [
            'items' => $lines,
            'subtotal' => $subtotal->amount,
            'discount_total' => $discountTotal->amount,
            'tax_total' => $tax->amount,
            'total' => $total->amount,
        ];
    }

    /** @return numeric-string */
    private function quantity(string $value, int $index): string
    {
        $value = trim($value);
        if (! is_numeric($value) || ! preg_match('/^\d{1,9}(\.\d{1,3})?$/', $value) || bccomp($value, '0', 3) <= 0) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'La cantidad debe ser mayor que cero (hasta 3 decimales).']);
        }

        return bcadd($value, '0', 3);
    }

    /** @return numeric-string */
    private function rate(string $value): string
    {
        $value = trim($value);
        if (! is_numeric($value) || ! preg_match('/^\d+(\.\d{1,4})?$/', $value) || bccomp($value, '1', 4) >= 0) {
            throw ValidationException::withMessages(['tax_rate' => 'La tasa de IVA no es válida.']);
        }

        return bcadd($value, '0', 4);
    }
}
