<?php

namespace App\Http\Requests\Concerns;

use App\Support\InvoiceClassification;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

trait ValidatesInvoiceClassification
{
    protected function prepareDocumentClassification(): void
    {
        $classification = [];
        if ($this->exists('document_type')) {
            $classification['document_type'] = InvoiceClassification::normalizeType($this->input('document_type'));
        }
        if ($this->exists('document_source')) {
            $classification['document_source'] = InvoiceClassification::normalizeSource($this->input('document_source'));
        }
        $this->merge($classification);
        if ($this->filled('invoice_type')) {
            $type = InvoiceClassification::fromLegacy($this->input('invoice_type'), null)['document_type'];
            if ($type !== null) $this->merge(['invoice_type' => $type === 'BILL' ? 'Bill' : 'Invoice']);
        }
        if (!$this->filled('invoice_type') && in_array($this->input('document_type'), InvoiceClassification::TYPES, true)) {
            $this->merge(['invoice_type' => $this->input('document_type') === 'BILL' ? 'Bill' : 'Invoice']);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['document_type', 'document_source', 'invoice_type', 'invoice_label'])) return;
            $legacy = InvoiceClassification::fromLegacy($this->input('invoice_type'), $this->input('invoice_label'));
            $type = $this->input('document_type') ?? $legacy['document_type'];
            $source = $this->input('document_source') ?? $legacy['document_source'];
            if ($this->filled('document_type') && $type !== $legacy['document_type']) {
                $validator->errors()->add('document_type', 'Document type must agree with invoice_type.');
            }
            try {
                InvoiceClassification::validate($type, $source);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) $validator->errors()->add($field, $message);
                }
            }
        });
    }
}
