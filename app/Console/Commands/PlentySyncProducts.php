<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Plenty\PlentyClient;
use Illuminate\Console\Command;

class PlentySyncProducts extends Command
{
    protected $signature = 'plenty:sync-products
        {--supplier= : Supplier ID (default: erster aktiver)}
        {--max-items=10000 : Obergrenze gespeicherter Pakete}
        {--start-page=1 : Plenty-Items-Seite, ab der gestartet wird}
        {--max-pages= : Wie viele Seiten in diesem Lauf höchstens (Chunking)}';

    protected $description = 'Plenty Item-Katalog synchronisieren. Bei Timeout-Risiko mit --max-pages chunked aufrufen.';

    public function handle(): int
    {
        @set_time_limit(0);

        $supplierId = $this->option('supplier');
        $supplier = $supplierId
            ? Supplier::find($supplierId)
            : Supplier::where('status', 'active')->orderBy('id')->first();

        if (! $supplier) {
            $this->error('Lieferant nicht gefunden.');

            return self::FAILURE;
        }

        $maxItems = (int) $this->option('max-items');
        $startPage = (int) $this->option('start-page');
        $maxPages = $this->option('max-pages') !== null ? (int) $this->option('max-pages') : null;

        $this->info("Supplier: #{$supplier->id} {$supplier->name}");
        $this->info("Start-Seite: {$startPage} · Max Seiten: ".($maxPages ?? 'unbegrenzt')." · Max Pakete: {$maxItems}");
        $this->info('Synchronisation läuft …');

        $start = microtime(true);
        try {
            $result = (new PlentyClient($supplier))->syncProducts(
                $maxItems,
                true,
                $startPage,
                $maxPages,
            );
        } catch (\Throwable $e) {
            $this->error('Fehler: '.$e->getMessage());

            return self::FAILURE;
        }
        $elapsed = round(microtime(true) - $start, 1);

        $this->newLine();
        $this->table(
            ['Feld', 'Wert'],
            [
                ['Verarbeitet', $result['processed']],
                ['Neu', $result['created']],
                ['Aktualisiert', $result['updated']],
                ['Varianten', $result['variations_synced']],
                ['Seiten gescannt', $result['pages_scanned']],
                ['Letzte Seite', $result['last_page']],
                ['Nächste Seite (für Resume)', $result['next_page']],
                ['Dauer (s)', $elapsed],
            ],
        );

        $this->info("Tipp: Resume mit --start-page={$result['next_page']}");

        return self::SUCCESS;
    }
}
