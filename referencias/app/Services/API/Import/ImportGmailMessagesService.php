<?php

namespace App\Services\API\Import;

use DateTime;
use Throwable;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\GmailMessagesLogService;
use App\DTO\GoogleAPI\GoogleAPIGmailMessageDTO;
use App\Services\API\GmailEmailNotificationService;
use App\Services\API\GoogleAPI\GoogleGmailAPIService;


class ImportGmailMessagesService
{

    protected $googleGmailAPIService;
    protected $gmailMessagesLogService;
    protected $gmailEmailNotificationService;


    public function __construct(
        GoogleGmailAPIService $googleGmailAPIService,
        GmailMessagesLogService $gmailMessagesLogService,
        GmailEmailNotificationService $gmailEmailNotificationService
    ) {
        $this->googleGmailAPIService = $googleGmailAPIService;
        $this->gmailMessagesLogService = $gmailMessagesLogService;
        $this->gmailEmailNotificationService = $gmailEmailNotificationService;
    }


    /**
     * @throws TokenHasNoPermissionsException
     * (from GoogleGmailAPIService::listMessages)
     */
    public function importUserMessages(User $user): Collection
    {
        $lastMsgDtos = $this->gmailMessagesLogService->findByUser($user, ['limit' => 5]);
        
        $minDate = null;
        $idsToIgnore = [];
        if ($lastMsgDtos->isNotEmpty()) {
            $minDate = $lastMsgDtos->first()->sentDate;
            $idsToIgnore = $lastMsgDtos->pluck('gmailId')->toArray();
        }
        
        // [HOTFIX] user_id: 2099. external_email_id: 3860802. No existe el email en clienty pero sí en mailer.
        // Lo ignoro, si vuelve a pasar reviso proceso de store al enviar email.
        $idsToIgnore[] = '186067b1821058c6';
        $idsToIgnore[] = '191bd8f11917d69f';

        $resultDtos = new Collection();
        $opts = ['minDate' => $minDate, 'idsToIgnore' => $idsToIgnore];
        $newGmailMsgDtos = $this->googleGmailAPIService->listMessages($user, $opts);
        $newGmailMsgDtos = $newGmailMsgDtos->reverse();
        foreach ($newGmailMsgDtos as $messageDto) {
            try {
                $existentMessageLog = $this->gmailMessagesLogService->findOneByClientAndGmailId(
                    $user->client, $messageDto->gmailId
                );
                if ($existentMessageLog) {
                    // $resultDtos->push(
                        // ['dto' => $messageDto, 'success' => false, 'error' => 'Message already stored']
                    // );
                    continue;
                }
                
                $dtoClientId = (int) $messageDto->clientyMetadata['client']['id'];
                if ($dtoClientId != $user->client->id) {
                    $resultDtos->push([
                        'dto' => $messageDto, 'success' => false, 'error' => 'Client ID does not match'
                    ]);
                    continue;
                }

                DB::beginTransaction();
                $storedDto = $this->gmailMessagesLogService->store($user->client, $messageDto);
                $this->gmailEmailNotificationService->createNewNotifications($user, $messageDto);

                $resultDtos->push(['dto' => $storedDto, 'success' => true, 'error' => null]);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }
        return $resultDtos;
    }

}
