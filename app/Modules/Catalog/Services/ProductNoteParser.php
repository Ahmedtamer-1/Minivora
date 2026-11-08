<?php

namespace App\Modules\Catalog\Services;

use App\Core\Money\Piasters;

/**
 * Reads a short product note such as "Brown leather duffel bag, 2,150 pounds, 6 pieces" or
 * "شنطة جلد بني، ٢١٥٠ جنيه، ٦ قطع" into a name, price and quantity. Deterministic stand-in until the
 * Store Builder agent drafts products (FR-ONB-05); anything it cannot read is left for the owner.
 */
class ProductNoteParser
{
    private const CURRENCY = '(?:egp|le|l\.e\.?|pounds?|pound|جنيه(?:ًا|ا)?|ج\.?م\.?|ج)';

    private const PIECES = '(?:pieces?|pcs?|units?|in stock|قطع(?:ة)?|حت(?:ة|ت)|وحد(?:ة|ات))';

    /** @return array{name: string, price: int|null, stock: int|null} */
    public function parse(string $note): array
    {
        $text = $this->westernDigits(trim($note));
        $text = preg_replace('/^(?:add|please add|أضف|اضف|ضيف)\s+/iu', '', $text) ?? $text;

        $price = null;
        $stock = null;

        if (preg_match('/(\d[\d,]*(?:\.\d{1,2})?)\s*'.self::CURRENCY.'/iu', $text, $m)
            || preg_match('/(?:for|at|بـ?|ب)\s*(\d[\d,]*(?:\.\d{1,2})?)/iu', $text, $m)) {
            $price = Piasters::fromPounds($m[1])->amount;
            $text = str_replace($m[0], '', $text);
        }

        if (preg_match('/(\d+)\s*'.self::PIECES.'/iu', $text, $m)) {
            $stock = (int) $m[1];
            $text = str_replace($m[0], '', $text);
        }

        // The name is what is left before the first separator.
        $name = preg_split('/[,،;\n]|\s+-\s+/u', $text)[0] ?? '';
        $name = trim(preg_replace('/\s+(?:for|at|بـ?|ب)\s*$/iu', '', trim($name)) ?? '', " \t.-");
        $name = mb_substr($name, 0, 120);

        return [
            'name' => $name === '' ? '' : mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1),
            'price' => $price,
            'stock' => $stock,
        ];
    }

    private function westernDigits(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٬' => ',', '٫' => '.',
        ]);
    }
}
