<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;


class UpdateLeadsHashCommand extends Command
{

    protected $description = 'Recalcula y actualiza el hash de los Leads (incluye eliminados)';
    protected $signature =
        'leads:update-hash {--chunk=} {--client-id=} {--min-id=} {--max-id=} {--ids=*} {--limit=}';
    

    public function handle()
    {
        $leadIds = $this->option('ids') ?? [];
        $chunk = (int) ($this->option('chunk') ?? 2000);
        $minId = (int) ($this->option('min-id') ?? 0);
        $maxId = (int) ($this->option('max-id') ?? 0);
        $clientId = (int) ($this->option('client-id') ?? 0);
        $limit = (int) ($this->option('limit') ?? 0);
        $this->warn('Este comando procesará los leads seleccionados y puede demorar.');

        // Incluimos eliminados lógicamente
        $queryBuilder = Lead::withTrashed();
        if ($clientId) {
            $queryBuilder->where('client_id', $clientId);
        }
        if ($minId) {
            $queryBuilder->where('id', '>=', $minId);
        }
        if ($maxId) {
            $queryBuilder->where('id', '<=', $maxId);
        }
        if ($leadIds) {
            $queryBuilder->whereIn('id', $leadIds);
        }
        $queryBuilder->orderBy('id', 'desc');
        // Cargamos relaciones y columnas necesarias para construir el hash eficientemente
        $queryBuilder->with([
            'mainLeadContact.leadContactPhones',
            'mainLeadContact.leadContactEmails',
        ]);
        $queryBuilder->select([
            'id',
            'hash',
            'deleted_at',
            'method',
            'company',
            'message',
            'other_fields',
        ]);
        
        $processedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $totalCount = (clone $queryBuilder)->count();
        $leadsCount = ($limit > 0) ? min($totalCount, $limit) : $totalCount;
        $this->info("Leads a procesar: {$leadsCount} (chunk={$chunk}" . ($limit > 0 ? ", limit={$limit}" : '') . ")");

        $queryBuilder->chunk($chunk, function ($leads) use (
            &$processedCount,
            &$updatedCount,
            &$skippedCount,
            &$errorCount,
            $leadsCount,
            $limit
        ) {
            $reachedLimit = false;
            foreach ($leads as $lead) {
                try {
                    $leadAttributes = [
                        'method' => $lead->method,
                        'company' => $lead->company,
                        'message' => $lead->message,
                        'other_fields' => $lead->other_fields,
                    ];
                    $mainLeadContactAttributes = [
                        'email' => $lead->main_email,
                        'phone' => $lead->main_phone,
                        'name' => $lead->mainLeadContact->name ?? '',
                        'last_name' => $lead->mainLeadContact->last_name ?? '',
                    ];

                    // Construcción del hash (con soporte a eliminados)
                    $newHash = Lead::buildHash($leadAttributes, $mainLeadContactAttributes);
                    if (!is_null($lead->deleted_at)) {
                        $newHash = Lead::buildDeletedHash($newHash);
                    }

                    if ($newHash == $lead->hash) {
                        $skippedCount++;
                        $processedCount++;
                        $this->line(
                            "[{$processedCount}/{$leadsCount}] Lead id={$lead->id}: sin cambios (hash coincide)"
                        );
                        continue;
                    }

                    $lead->hash = $newHash;
                    $lead->save();
                    $updatedCount++;
                    $processedCount++;
                    $this->info("[{$processedCount}/{$leadsCount}] Lead id={$lead->id}: hash actualizado");
                } catch (\Throwable $e) {
                    $errorCount++;
                    $processedCount++;
                    $this->error("[{$processedCount}/{$leadsCount}] Lead id={$lead->id}: ERROR - " . $e->getMessage());
                }

                if ($limit > 0 && $processedCount >= $limit) {
                    $reachedLimit = true;
                    break;
                }
            }
            if ($reachedLimit) {
                return false; // Detiene el chunking
            }
        });

        $this->info('--- Resumen ---');
        $this->info("Procesados: {$processedCount}");
        $this->info("Actualizados: {$updatedCount}");
        $this->info("Sin cambios: {$skippedCount}");
        $this->info("Con errores: {$errorCount}");
    }

}
