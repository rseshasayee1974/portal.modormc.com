<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:37:18 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Gst;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class TallyJournalExport
{
    public function guid(int $plant, int $journal): string
    {
        return (string) Uuid::uuid5(Uuid::NAMESPACE_URL, config('gst_compliance.tally_namespace').':plant:'.$plant.':journal:'.$journal);
    }

    public function build(int $plant, string $period, string $company, ?int $journal = null): string
    {
        GstData::period($period);
        abort_unless(trim($company) !== '' && strlen($company) <= 200, 422, 'Specify the exact Tally company name');

        return DB::transaction(function () use ($plant, $period, $company, $journal) {
            // Freeze each export's read set against the main invoice posting path.
            DB::table('mm_plants')->where('id', $plant)->lockForUpdate()->first();
            $entries = DB::table('mm_journal_entries')->where('plant_id', $plant)->whereNull('deleted_at')->whereRaw("LOWER(is_status)='posted'")
                ->when($journal, fn ($q) => $q->where('id', $journal))
                ->where(fn ($q) => $q->whereNull('ref_module')->orWhere('ref_module', '!=', 'tally_import'))
                ->whereBetween('voucher_date', [$period.'-01', date('Y-m-t', strtotime($period.'-01'))])->orderBy('id')->get();
            abort_unless($entries->isNotEmpty(), 422, 'No posted journals for this period');
            abort_if($entries->count() > 5000, 422, 'Export limit is 5000 journals per month');
            $allLines = DB::table('mm_journal_entry_lines as l')->leftJoin('mm_ledgers as a', 'a.id', '=', 'l.account_id')
                ->whereIn('l.journal_entry_id', $entries->pluck('id'))->whereNull('l.deleted_at')->orderBy('l.id')
                ->select('l.*', 'a.title as ledger_title', 'a.plant_id as ledger_plant', 'a.deleted_at as ledger_deleted')->get()->groupBy('journal_entry_id');
            $d = new \DOMDocument('1.0', 'UTF-8');
            $d->formatOutput = true;
            $root = $d->appendChild($d->createElement('ENVELOPE'));
            $add = function ($parent, string $tag, string $text = '') use ($d) {
                $e = $parent->appendChild($d->createElement($tag));
                if ($text !== '') {
                    $e->appendChild($d->createTextNode($text));
                }

                return $e;
            };
            $add($add($root, 'HEADER'), 'TALLYREQUEST', 'Import Data');
            $import = $add($add($root, 'BODY'), 'IMPORTDATA');
            $desc = $add($import, 'REQUESTDESC');
            $add($desc, 'REPORTNAME', 'Vouchers');
            $add($add($desc, 'STATICVARIABLES'), 'SVCURRENTCOMPANY', $company);
            $request = $add($import, 'REQUESTDATA');
            foreach ($entries as $j) {
                $lines = $allLines->get($j->id, collect());
                $dr = 0;
                $cr = 0;
                abort_unless($lines->count() >= 2, 422, 'Journal lacks balanced lines: '.$j->id);
                $guid = $this->guid($plant, $j->id);
                $v = $add($add($request, 'TALLYMESSAGE'), 'VOUCHER');
                $v->setAttribute('REMOTEID', $guid);
                $v->setAttribute('VCHTYPE', 'Journal');
                $v->setAttribute('ACTION', 'Create');
                $v->setAttribute('OBJVIEW', 'Accounting Voucher View');
                $add($v, 'GUID', $guid);
                $add($v, 'DATE', str_replace('-', '', substr($j->voucher_date, 0, 10)));
                $add($v, 'VOUCHERTYPENAME', 'Journal');
                $add($v, 'VOUCHERNUMBER', 'WEB-'.$j->id);
                $add($v, 'PERSISTEDVIEW', 'Accounting Voucher View');
                $add($v, 'NARRATION', $j->narration ?? '');
                foreach ($lines as $l) {
                    abort_unless((int) $l->plant_id === $plant && (int) $l->ledger_plant === $plant && ! $l->ledger_deleted && $l->ledger_title, 422, 'Invalid ledger ownership in journal '.$j->id);
                    $debit = GstData::paise($l->debit_amount);
                    $credit = GstData::paise($l->credit_amount);
                    abort_unless($debit >= 0 && $credit >= 0 && (($debit > 0) !== ($credit > 0)), 422, 'Invalid journal line '.$l->id);
                    $dr += $debit;
                    $cr += $credit;
                    $e = $add($v, 'ALLLEDGERENTRIES.LIST');
                    $name = config('gst_compliance.tally_ledger_map.'.$l->account_id, $l->ledger_title);
                    $add($e, 'LEDGERNAME', $name);
                    $add($e, 'ISDEEMEDPOSITIVE', $debit > 0 ? 'Yes' : 'No');
                    $amount = $credit - $debit;
                    $add($e, 'AMOUNT', ($amount < 0 ? '-' : '').intdiv(abs($amount), 100).'.'.str_pad((string) (abs($amount) % 100), 2, '0', STR_PAD_LEFT));
                }
                abort_unless($dr === $cr && $dr > 0 && $dr === GstData::paise($j->total_debit) && $cr === GstData::paise($j->total_credit), 422, 'Unbalanced header or lines in journal '.$j->id);
            }

            return $d->saveXML();
        });
    }

    public function outcome(string $xml): string
    {
        if (strlen($xml) > 1048576 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            return 'uncertain';
        }
        $prior = libxml_use_internal_errors(true);
        try {
            $d = new \DOMDocument;
            if (! $d->loadXML($xml, LIBXML_NONET)) {
                return 'uncertain';
            }$x = new \DOMXPath($d);
            $value = function (string $tag) use ($x) {
                $n = $x->query('//'.$tag);

                return $n->length === 1 && ctype_digit(trim($n->item(0)->textContent)) ? (int) trim($n->item(0)->textContent) : null;
            };
            if ($value('ERRORS') === null || $value('CREATED') === null || $x->query('//LINEERROR')->length) {
                return 'uncertain';
            }
            if ($value('ERRORS') > 0) {
                return 'failed';
            }
            if ($value('CREATED') !== 1 || ($value('LASTVCHID') ?? 0) < 1) {
                return 'uncertain';
            }
            foreach (['ALTERED', 'DELETED', 'CANCELLED', 'IGNORED', 'EXCEPTIONS'] as $tag) {
                if (($value($tag) ?? 0) !== 0) {
                    return 'uncertain';
                }
            }

            return 'confirmed';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prior);
        }
    }
}
