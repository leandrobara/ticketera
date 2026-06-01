<?php

namespace App\Console\Commands;

use Exception;
use DateTime;
use App\Models\NPSPoll;
use App\Models\NPSPollAnswer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class NPSPollFixAnswersCommand extends Command
{

    protected $signature = 'nps:fix-answers {--poll-id=}';
    protected $description = 'Corrige las respuestas de una encuesta NPS específica';


    public function handle()
    {
        $pollId = $this->option('poll-id');
        if (!$pollId) {
            $this->error('--poll-id argument is empty');
            return 1;
        }
        $poll = NPSPoll::findOrFail($pollId);

        $dateNow = new DateTime();
        $answers = NPSPollAnswer::withTrashed()->where('nps_poll_id', $pollId)->get();
        $groupedAnswers = $answers->groupBy(['client_id', 'user_id']);
        
        $newAnswers = [];
        $this->info("Corrigiendo respuestas para la encuesta NPS ID: {$pollId}");

        foreach ($groupedAnswers as $clientId => $userAnswers) {
            foreach ($userAnswers as $userId => $answers) {
                $latestAnswer = $answers->sortByDesc('closed_date')->first();
                $latestScoredAnswer = $answers->whereNotNull('score')->sortByDesc('created_at')->first();
                $newestScore = $latestScoredAnswer ? $latestScoredAnswer->score : null;
                $comments = $answers->whereNotNull('comments')->pluck('comments')->filter()->unique()->implode(' | ');

                $newAnswers[] = [
                    'nps_poll_id' => $pollId,
                    'client_id' => $clientId,
                    'user_id' => $userId,
                    'score' => $newestScore,
                    'comments' => $comments ?: null,
                    'created_at' => $dateNow,
                    'updated_at' => $dateNow,
                    'closed_date' => $latestAnswer->closed_date,
                    'close_reason' => $latestAnswer->close_reason,
                ];
            }
        }

        $filteredNewAnswers = collect(array_map(function ($answer) {
            return [
                'nps_poll_id' => $answer['nps_poll_id'],
                'client_id' => $answer['client_id'],
                'user_id' => $answer['user_id'],
                'score' => $answer['score'],
                'comments' => $answer['comments'],
                'closed_date' => $answer['closed_date'],
                'close_reason' => $answer['close_reason'],
            ];
        }, $newAnswers));

        DB::beginTransaction();

        try {
            NPSPollAnswer::where('nps_poll_id', $pollId)->update([
                'deleted_at' => $dateNow,
                'deleted_at_ts' => $dateNow->getTimestamp(),
            ]);

            NPSPollAnswer::insert($newAnswers);

            DB::commit();
            $this->info('Corrección de respuestas completada con éxito.');
        } catch (Exception $e) {
            DB::rollBack();
            $this->error('Error al corregir las respuestas: ' . $e->getMessage());
        }
    }

}