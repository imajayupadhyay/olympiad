<?php

namespace App\Models;

use App\Support\GstStateCodes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReceiptSetting extends Model
{
    protected $fillable = [
        'company_name',
        'gstin',
        'address',
        'state',
        'state_code',
        'email',
        'phone',
        'website',
        'logo_path',
        'hsn_sac',
        'service_description',
        'gst_rate',
        'prices_include_gst',
        'receipt_prefix',
        'receipt_padding',
        'financial_year_start_month',
        'visible_fields',
        'footer_note',
    ];

    protected $casts = [
        'gst_rate' => 'decimal:2',
        'prices_include_gst' => 'boolean',
        'receipt_padding' => 'integer',
        'financial_year_start_month' => 'integer',
        'visible_fields' => 'array',
    ];

    public const DEFAULT_VISIBLE_FIELDS = [
        'logo',
        'gstin',
        'address',
        'email',
        'phone',
        'website',
        'student_email',
        'student_phone',
        'hsn_sac',
        'tax_breakup',
        'payment_ids',
        'footer_note',
    ];

    public const VISIBLE_FIELD_LABELS = [
        'logo' => 'Company logo',
        'gstin' => 'Company GSTIN',
        'address' => 'Company address',
        'email' => 'Company email',
        'phone' => 'Company phone',
        'website' => 'Website',
        'student_email' => 'Student email',
        'student_phone' => 'Student phone',
        'hsn_sac' => 'HSN/SAC',
        'tax_breakup' => 'GST breakup',
        'payment_ids' => 'Gateway payment IDs',
        'footer_note' => 'Footer note',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], static::defaults());
    }

    public static function defaults(): array
    {
        return [
            'company_name' => 'National Olympiad Hunt',
            'service_description' => 'Online Olympiad Exam Registration',
            'gst_rate' => 18,
            'prices_include_gst' => true,
            'receipt_prefix' => 'NEO/{FY}/',
            'receipt_padding' => 4,
            'financial_year_start_month' => 4,
            'visible_fields' => static::DEFAULT_VISIBLE_FIELDS,
            'footer_note' => 'This is a computer-generated receipt and does not require a physical signature.',
        ];
    }

    public function visible(string $field): bool
    {
        return in_array($field, $this->visible_fields ?: static::DEFAULT_VISIBLE_FIELDS, true);
    }

    /**
     * Printable state, e.g. "Delhi / 07". Falls back to whichever half is set.
     */
    public function stateDisplay(): ?string
    {
        return GstStateCodes::format($this->state, $this->state_code);
    }

    public function logoUrl(): string
    {
        if ($this->logo_path) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return asset('/NEO_logo_horizontal_transparent.svg');
    }

    public function logoDataUri(): ?string
    {
        $path = $this->logo_path
            ? Storage::disk('public')->path($this->logo_path)
            : public_path('NEO_logo_horizontal_transparent.svg');

        if (! is_file($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => mime_content_type($path) ?: 'application/octet-stream',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * Company details used at PDF render time.
     *
     * Receipt rows still keep their original issued number, dates, customer,
     * payment, and tax totals. These settings are intentionally read fresh so
     * old receipts and reports can reflect updated business details.
     */
    public function renderCompanyPayload(): array
    {
        return [
            'name' => $this->company_name,
            'gstin' => $this->gstin,
            'address' => $this->address,
            'state' => $this->state,
            'state_code' => $this->state_code,
            'state_display' => $this->stateDisplay(),
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'logo_data_uri' => $this->visible('logo') ? $this->logoDataUri() : null,
            'hsn_sac' => $this->hsn_sac,
            'service_description' => $this->service_description,
            'gst_rate' => (float) $this->gst_rate,
            'prices_include_gst' => (bool) $this->prices_include_gst,
            'visible_fields' => $this->visible_fields ?: static::DEFAULT_VISIBLE_FIELDS,
            'footer_note' => $this->footer_note,
        ];
    }

    public function financialYear(?CarbonInterface $date = null): string
    {
        $date ??= now();
        $startMonth = max(1, min(12, (int) $this->financial_year_start_month));
        $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }

    public function renderPrefix(?CarbonInterface $date = null): string
    {
        $date ??= now();
        $financialYear = $this->financialYear($date);

        return strtr((string) $this->receipt_prefix, [
            '{FY}' => $financialYear,
            '{YYYY}' => (string) $date->year,
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
        ]);
    }

    public function formatReceiptNumber(int $sequence, ?CarbonInterface $date = null): string
    {
        return $this->renderPrefix($date).str_pad((string) $sequence, (int) $this->receipt_padding, '0', STR_PAD_LEFT);
    }
}
