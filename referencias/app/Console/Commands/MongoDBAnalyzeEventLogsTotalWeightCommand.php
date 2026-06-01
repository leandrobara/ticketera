<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MongoDBAnalyzeEventLogsTotalWeightCommand extends Command
{
    protected $signature = 'mongodb:analyze-event-logs-weight
        {--chunk=40000 : Tamaño del chunk a procesar}
        {--collection=logs : Nombre de la colección}';

    protected $description = 'Analiza eventos en MongoDB usando consultas raw para máxima eficiencia';

    // Contadores y acumuladores
    protected $eventCounts = [];
    protected $eventSizes = [];
    protected $totalDocuments = 0;
    protected $totalSize = 0;
    protected $processedDocuments = 0;
    protected $connection;
    protected $collection;
    protected $startTime;


    public function handle()
    {
        $this->chunkSize = (int) $this->option('chunk');
        $this->collection = $this->option('collection');
        
        $this->info("Iniciando análisis raw de eventos en la colección: {$this->collection}");
        
        try {
            // Obtener la conexión MongoDB desde Laravel
            $this->connection = DB::connection('mongodb_logs');
            
            // Verificar que podemos conectarnos
            $this->info("Verificando conexión a MongoDB...");
            $totalDocs = $this->connection->collection($this->collection)->count();
            $this->info("Conexión exitosa. Total de documentos: " . number_format($totalDocs));
            
            if ($totalDocs > 0) {
                // Obtener un documento de muestra para ver su estructura
                $sample = $this->connection->collection($this->collection)->first();
                $this->info("Estructura de documento de muestra:");
                foreach ($sample as $key => $value) {
                    $type = is_object($value) ? get_class($value) : gettype($value);
                    $this->line(" - {$key}: {$type}");
                }
                
                $this->totalDocuments = $totalDocs;
                $this->startTime = microtime(true);
                $this->analyzeInChunks();
                $this->displayResults();
            } else {
                $this->warn("No se encontraron documentos en la colección.");
            }
        } catch (\Exception $e) {
            $this->error("Error al conectar con MongoDB: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function analyzeInChunks()
    {
        $progressBar = $this->output->createProgressBar($this->totalDocuments);
        $progressBar->start();
        
        $lastId = null;
        $processed = 0;
        
        do {
            // Construir la consulta para el siguiente chunk
            $query = [];
            if ($lastId !== null) {
                $query['_id'] = ['$gt' => $lastId];
            }
            
            // Ejecutar consulta raw para mayor eficiencia
            $documents = $this->connection->collection($this->collection)
                ->raw(function ($collection) use ($query) {
                    return $collection->find(
                        $query,
                        [
                            'limit' => (int) $this->chunkSize,
                            'sort' => ['_id' => 1]
                        ]
                    );
                });
            
            $count = 0;
            $batchDocs = [];
            
            foreach ($documents as $document) {
                $count++;
                $this->analyzeDocument($document);
                $lastId = $document['_id'];
            }
            
            if ($count > 0) {
                $this->processedDocuments += $count;
                $processed += $count;
                $progressBar->advance($count);
                
                // Mostrar progreso periódicamente
                if ($processed >= $this->chunkSize * 10) {
                    $processed = 0;
                    $this->line("");

                    $elapsed = microtime(true) - $this->startTime;
                    $docsPerSecond = $elapsed > 0 ? $this->processedDocuments / $elapsed : 0;
                    $remainingDocs = $this->totalDocuments - $this->processedDocuments;
                    $etaSeconds = $docsPerSecond > 0 ? $remainingDocs / $docsPerSecond : 0;
                    $etaFormatted = gmdate("H:i:s", (int) $etaSeconds);

                    $this->info("Procesados {$this->processedDocuments} de {$this->totalDocuments} documentos");
                    $this->info("Tiempo estimado restante: {$etaFormatted}");
                    
                    // Mostrar algunos stats intermedios
                    $this->line("Top 5 eventos hasta ahora (cantidad y peso total):");
                    $topEvents = array_slice($this->getTopEvents(), 0, 5);
                    foreach ($topEvents as $event => $count) {
                        $totalSize = $this->eventSizes[$event] ?? 0;
                        $avgSize = $count > 0 ? $totalSize / $count : 0;
                        $this->line(
                            " - {$event}: {$count} eventos, " . $this->formatBytes($totalSize) .
                            " total, " . $this->formatBytes($avgSize) . " promedio")
                        ;
                    }
                    
                    // Mostrar uso de memoria
                    $this->line("Uso de memoria: " . $this->formatBytes(memory_get_usage(true)));
                }
            }
        } while ($count > 0);
        
        $progressBar->finish();
        $this->line("");
    }
    
    protected function analyzeDocument($document)
    {
        // Determinar el tipo de evento
        $eventType = $document['event'] ?? 'unknown';
        $system = $document['system'] ?? 'unknown';
        
        // Combinar sistema y evento para una clasificación más específica
        $eventKey = "{$system}:{$eventType}";
        
        // Contar ocurrencias
        if (!isset($this->eventCounts[$eventKey])) {
            $this->eventCounts[$eventKey] = 0;
            $this->eventSizes[$eventKey] = 0;
        }
        $this->eventCounts[$eventKey]++;
        
        // Calcular tamaño aproximado del documento
        $docSize = $this->calculateDocumentSize($document);
        $this->eventSizes[$eventKey] += $docSize;
        $this->totalSize += $docSize;
    }
    
    protected function calculateDocumentSize($document)
    {
        // Convertir a JSON y medir la longitud para aproximar el tamaño
        $json = json_encode($document);
        return strlen($json);
    }
    
    protected function getTopEvents()
    {
        $counts = $this->eventCounts;
        arsort($counts);
        return $counts;
    }
    
    protected function displayResults()
    {
        $this->info("\n===== Resultados del Análisis =====");
        $this->info("Total de documentos procesados: " . number_format($this->processedDocuments));
        $this->info("Tamaño total aproximado: " . $this->formatBytes($this->totalSize));
        
        $this->info("\nTop 20 eventos por cantidad:");
        $topEvents = array_slice($this->getTopEvents(), 0, 20);
        $headers = ['Evento', 'Cantidad', 'Tamaño Total', 'Tamaño Promedio', '% del Total'];
        $rows = [];
        
        foreach ($topEvents as $event => $count) {
            $totalSize = $this->eventSizes[$event];
            $avgSize = $count > 0 ? $totalSize / $count : 0;
            $percentage = ($count / $this->processedDocuments) * 100;
            
            $rows[] = [
                $event,
                number_format($count),
                $this->formatBytes($totalSize),
                $this->formatBytes($avgSize),
                number_format($percentage, 2) . '%'
            ];
        }
        
        $this->table($headers, $rows);
        
        // Guardar resultados en archivo
        $outputPath = storage_path('logs/mongodb_analysis_' . date('Y-m-d_H-i-s') . '.json');
        $output = [
            'timestamp' => date('Y-m-d H:i:s'),
            'collection' => $this->collection,
            'totalDocuments' => $this->processedDocuments,
            'totalSize' => $this->totalSize,
            'eventCounts' => $this->eventCounts,
            'eventSizes' => $this->eventSizes
        ];
        
        file_put_contents($outputPath, json_encode($output, JSON_PRETTY_PRINT));
        $this->info("Resultados detallados guardados en: {$outputPath}");
    }
    
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}