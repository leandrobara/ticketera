<?php
namespace App\Providers;

use Pusher\Pusher;
use App\Helpers\WAPIHelper;
use App\Helpers\LockHelper;
use App\Helpers\RedisHelper;
use App\Helpers\PhonesHelper;
use App\Helpers\StringHelper;
use App\Helpers\OpenAIHelper;
use App\Helpers\GoogleAPIHelper;
use App\Services\API\LeadService;
use App\Services\API\UserService;
use App\Helpers\FacebookAdHelper;
use App\Helpers\MondayAPIHelper2;
use App\Helpers\QueuedJobsCounter;
use App\Helpers\MongoSearchHelper;
use App\Helpers\CalendlyAPIHelper;
use App\Helpers\FacebookPageHelper;
use App\Helpers\LeadAudioNoteHelper;
use App\Helpers\GoogleGmailAPIHelper;
use App\Helpers\IPQualityScoreHelper;
use App\Helpers\LeadAttachmentHelper;
use Illuminate\Foundation\Application;
use App\Helpers\MailsSoValidatorHelper;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Support\ServiceProvider;
use App\Helpers\WhatsAppAttachmentHelper;
use App\Helpers\AudioEncodingLambdaHelper;
use App\Helpers\GmailMessagesLogAPIHelper;
use App\Helpers\SpreadSheetLeadImportHelper;
use App\Helpers\ClientyMailerValidatorHelper;
use App\Helpers\EmailListVerifyValidatorHelper;
use App\Helpers\AutomationUserNotificationHelper;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Helpers\WapSalesAgent\WapSalesAgentPromptHelper;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationRealTimeHelper;

class HelpersServiceProvider extends ServiceProvider
{

    public function boot()
    {
    }


    public function register()
    {
        $this->app->singleton(MongoSearchHelper::class, function () {
            return new MongoSearchHelper(
                config('database.connections.mongodb_leads_search.host'),
                config('database.connections.mongodb_leads_search.database'),
                config('database.connections.mongodb_leads_search.username'),
                config('database.connections.mongodb_leads_search.password'),
                config('database.connections.mongodb_leads_search.collection'),
                config('database.connections.mongodb_leads_search.is_atlas')
            );
        });

        $this->app->singleton(PhonesHelper::class, function () {
            return new PhonesHelper();
        });

        $this->app->singleton(LockHelper::class, function () {
            return new LockHelper();
        });
        
        $this->app->bind(QueuedJobsCounter::class, function (Application $app, array $params) {
            $ttlSeconds = $params['ttlSeconds'] ?? 10;
            return new QueuedJobsCounter($ttlSeconds);
        });

        $this->app->singleton(AutomationUserNotificationHelper::class, function () {
            return new AutomationUserNotificationHelper();
        });


        $this->app->singleton(Pusher::class, function () {
            $pusher = new Pusher(
                config('broadcasting.connections.pusher_wap_sender.key'),
                config('broadcasting.connections.pusher_wap_sender.secret'),
                config('broadcasting.connections.pusher_wap_sender.app_id'),
                ['cluster' => config('broadcasting.connections.pusher_wap_sender.options.cluster')]
            );
            return $pusher;
        });
        
        $this->app->singleton(AudioEncodingLambdaHelper::class, function () {
            return new AudioEncodingLambdaHelper(
                config('app.lambda.ogg_audio_function_secret_key'),
            );
        });
        

        // Deprecados, no existe más clienty logger.
        // $this->app->singleton(EventsLogAPIHelper::class, function () {
        //     return new EventsLogAPIHelper(
        //         config('app.clienty_logger.route'),
        //         config('app.clienty_logger.clienty_crm_service'),
        //         config('app.clienty_logger.secret'),
        //         config('app.clienty_logger.timeout'),
        //         config('app.clienty_logger.jwt_secret'),
        //         config('app.clienty_logger.jwt_algo')
        //     );
        // });
        
        // $this->app->singleton(WAPIMessagesLogAPIHelper::class, function () {
        //     return new WAPIMessagesLogAPIHelper(
        //         config('app.clienty_logger.route'),
        //         config('app.clienty_logger.secret'),
        //         config('app.clienty_logger.timeout'),
        //         config('app.clienty_logger.jwt_secret'),
        //         config('app.clienty_logger.jwt_algo')
        //     );
        // });

        // $this->app->singleton(GmailMessagesLogAPIHelper::class, function () {
        //     return new GmailMessagesLogAPIHelper(
        //         config('app.clienty_logger.route'),
        //         config('app.clienty_logger.secret'),
        //         config('app.clienty_logger.timeout'),
        //         config('app.clienty_logger.jwt_secret'),
        //         config('app.clienty_logger.jwt_algo')
        //     );
        // });


        // @TODO Replace all this variables in constructor with a DTO or something like that.
        $this->app->singleton(ClientyMailerAPIHelper::class, function () {
            return new ClientyMailerAPIHelper(
                resolve(ClientyMailerValidatorHelper::class),
                config('app.clienty_mailer.route'),
                config('app.clienty_mailer.jwt_secret'),
                config('app.clienty_mailer.jwt_algo'),
                config('app.clienty_mailer.timeout')
            );
        });

        $this->app->singleton(WAPIHelper::class, function ($app, $params = []) {
            $wapiHelper = new WAPIHelper(
                wapEngine: 'wwebjs', // se carga por default, luego se cambia
                timeout: config('app.wapi.timeout'),
                wapiRoute: config('app.wapi.default_route'), // se carga por default, luego se cambia
                clientyJwtAlgo: config('app.wapi.jwt_algo'),
                clientyJwtSecret: config('app.wapi.jwt_secret'),
            );

            $user = $params['user'] ?? null;
            if ($user) {
                $wapiHelper->setRouteAndEngineFromUser($user);
            }
            return $wapiHelper;
        });

        $this->app->singleton(FacebookPageHelper::class, function () {
            return new FacebookPageHelper(
                fbApp: config('app.facebook.app'),
                fbSecret: config('app.facebook.secret'),
                redisHelper: resolve(RedisHelper::class),
                fbVersion: config('app.facebook.version'),
                handleUrl: config('app.facebook.handle_url'),
                suscribedFields: config('app.facebook.subscribed_fields'),
                subscriptionScope: config('app.facebook.subscription_scope'),
            );
        });

        $this->app->singleton(WhatsAppMetaAPIHelper::class, function () {
            return new WhatsAppMetaAPIHelper(
                appId: config('app.facebook.app'),
                appSecret: config('app.facebook.secret'),
            );
        });

        $this->app->singleton(FacebookAdHelper::class, function () {
            return new FacebookAdHelper(
                config('app.facebook.app'),
                config('app.facebook.secret')
            );
        });

        $this->app->singleton(ClientyMailerValidatorHelper::class, function () {
            return new ClientyMailerValidatorHelper();
        });

        $this->app->singleton(FileImportHelper::class, function () {
            return new SpreadSheetLeadImportHelper(
                resolve(UserService::class),
                resolve(LeadService::class)
            );
        });

        $this->app->singleton(LeadAttachmentHelper::class, function () {
            return new LeadAttachmentHelper('lead_attachments');
        });

        $this->app->singleton(LeadAudioNoteHelper::class, function () {
            return new LeadAudioNoteHelper('lead_audionote_attachments');
        });

        $this->app->singleton(WhatsAppAttachmentHelper::class, function () {
            return new WhatsAppAttachmentHelper('whatsapp_attachments');
        });

        $this->app->singleton(IPQualityScoreHelper::class, function () {
            return new IPQualityScoreHelper(config('app.ip_quality_score.api_key'));
        });

        $this->app->singleton(MailsSoValidatorHelper::class, function () {
            return new MailsSoValidatorHelper(config('app.mails_so.api_key'));
        });

        $this->app->singleton(EmailListVerifyValidatorHelper::class, function () {
            return new EmailListVerifyValidatorHelper(config('app.email_list_verify.api_key'));
        });

        $this->app->singleton(GoogleAPIHelper::class, function () {
            return new GoogleAPIHelper(config('google_api.credentials'));
        });

        $this->app->singleton(GoogleGmailAPIHelper::class, function () {
            return new GoogleGmailAPIHelper();
        });

        $this->app->singleton(StringHelper::class, function () {
            return new StringHelper();
        });

        $this->app->singleton(CalendlyAPIHelper::class, function () {
            return new CalendlyAPIHelper(
                config('app.calendly.access_token'),
                config('app.calendly.organization_id'),
            );
        });

        $this->app->singleton(MondayAPIHelper2::class, function () {
            return new MondayAPIHelper2(
                apiToken: config('app.monday.api_token'),
                obBoardId: config('app.monday.ob_board_id'),
                npsBoardId: config('app.monday.nps_board_id'),
                churnBoardId: config('app.monday.churn_board_id'),
            );
        });

        $this->app->singleton(OpenAIHelper::class, function () {
            return new OpenAIHelper(
                config('app.openai.api_key'),
            );
        });

        $this->app->singleton(WapSalesAgentPromptHelper::class, function () {
            return new WapSalesAgentPromptHelper();
        });

        // App de Pusher dedicada para tiempo real de conversaciones de WhatsApp.
        // Separada de pusher (app principal) y pusher_wap_sender (WAPSender).
        $this->app->singleton(WhatsAppConversationRealTimeHelper::class, function () {
            $pusher = new Pusher(
                config('broadcasting.connections.pusher_whatsapp_conversations.key'),
                config('broadcasting.connections.pusher_whatsapp_conversations.secret'),
                config('broadcasting.connections.pusher_whatsapp_conversations.app_id'),
                ['cluster' => config('broadcasting.connections.pusher_whatsapp_conversations.options.cluster')]
            );
            return new WhatsAppConversationRealTimeHelper($pusher);
        });
    }

}
