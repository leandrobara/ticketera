<?php

namespace App\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

use App\Helpers\WAPIHelper;
use App\Helpers\RedisHelper;
use App\Helpers\PhonesHelper;
use App\Helpers\GoogleAPIHelper;
use App\Helpers\FacebookAdHelper;
use App\Helpers\MondayAPIHelper2;
use App\Helpers\MongoSearchHelper;
use App\Helpers\FacebookPageHelper;
use App\Helpers\MermaidChartHelper;
use App\Helpers\ElasticSearchHelper;
use App\Helpers\EmailVariablesHelper;
use App\Helpers\EmailValidatorHelper;
use App\Helpers\GoogleGmailAPIHelper;
use App\Helpers\ClientyMailerAPIHelper;
use App\Helpers\WhatsAppAttachmentHelper;
use App\Helpers\SpreadSheetLeadImportHelper;
use App\Helpers\AutomationUserNotificationHelper;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;

use App\Models\Tag;
use App\Models\Lead;
use App\Models\Note;
use App\Models\LeadContact;
use App\Models\LeadAttachment;
use App\Models\StatusCategory;
use App\Models\TemplateCategory;
use App\Models\LeadContactEmail;
use App\Models\LeadContactPhone;
use App\Models\GoogleAPIUserToken;
use App\Models\WhatsAppAttachment;
use App\Models\WAutomationSequence;
use App\Models\AutomationEmailSend;
use App\Models\GoogleAPIUserContact;
use App\Models\WAutomationAfterSend;
use App\Models\GmailEmailNotification;
use App\Models\WAutomationSequenceStep;
use App\Models\AutomationEmailSendStep;
use App\Models\AutomationProposalResendRule;
use App\Models\WAutomationProposalResendRule;
use App\Models\AutomationProposalInteractionRule;
use App\Models\WAutomationProposalInteractionRule;
use App\Models\AutomationProposalModifyLeadAfterSendRule;
use App\Models\WAutomationProposalModifyLeadAfterSendRule;

use App\Observers\TagObserver;
use App\Observers\LeadObserver;
use App\Observers\NoteObserver;
use App\Observers\LeadContactObserver;
use App\Observers\StatusCategoryObserver;
use App\Observers\LeadAttachmentObserver;
use App\Observers\TemplateCategoryObserver;
use App\Observers\LeadContactEmailObserver;
use App\Observers\LeadContactPhoneObserver;
use App\Observers\WhatsAppAttachmentObserver;
use App\Observers\GoogleAPIUserTokenObserver;
use App\Observers\AutomationEmailSendObserver;
use App\Observers\WAutomationSequenceObserver;
use App\Observers\GoogleAPIUserContactObserver;
use App\Observers\WAutomationAfterSendObserver;
use App\Observers\AutomationEmailSendStepObserver;
use App\Observers\WAutomationSequenceStepObserver;
use App\Observers\AutomationProposalResendRuleObserver;
use App\Observers\WAutomationProposalResendRuleObserver;
use App\Observers\AutomationProposalInteractionRuleObserver;
use App\Observers\AutomationProposalModifyLeadAfterSendRuleObserver;
use App\Observers\WAutomationProposalModifyLeadAfterSendRuleObserver;

use App\Repositories\TagRepository;
use App\Repositories\LeadRepository;
use App\Repositories\NoteRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Repositories\EmailRepository;
use App\Repositories\ClientRepository;
use App\Repositories\StatusRepository;
use App\Repositories\LandingRepository;
use App\Repositories\ManagerRepository;
use App\Repositories\NPSPollRepository;
use App\Repositories\LeadSaleRepository;
use App\Repositories\UserLoginRepository;
use App\Repositories\EventsLogRepository;
use App\Repositories\AttachmentRepository;
use App\Repositories\LeadContactRepository;
use App\Repositories\ProposalInfoRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\TaskTemplateRepository;
use App\Repositories\EmailTemplateRepository;
use App\Repositories\NPSPollAnswerRepository;
use App\Repositories\WapBot\WapBotRepository;
use App\Repositories\ClientSettingsRepository;
use App\Repositories\LeadCustomFieldRepository;
use App\Repositories\WhatsAppSendingRepository;
use App\Repositories\ProposalInfoTmpRepository;
use App\Repositories\WhatsAppTemplateRepository;
use App\Repositories\NewsNotificationRepository;
use App\Repositories\LeadContactEmailRepository;
use App\Repositories\LeadContactPhoneRepository;
use App\Repositories\UserCustomFilterRepository;
use App\Repositories\AcquisitionChannelRepository;
use App\Repositories\ClientFacebookPageRepository;
use App\Repositories\GoogleAPIUserTokenRepository;
use App\Repositories\WhatsAppAttachmentRepository;
use App\Repositories\EmailNotificationLogRepository;
use App\Repositories\GoogleAPIUserContactRepository;
use App\Repositories\WhatsAppQuickResponseRepository;
use App\Repositories\LeadNotificationEmailRepository;
use App\Repositories\TaskNotificationEmailRepository;
use App\Repositories\WhatsAppSendingMessageRepository;
use App\Repositories\GmailEmailNotificationRepository;
use App\Repositories\WapBot\WapBotConversationRepository;
use App\Repositories\ClientyConfigEmailTemplateRepository;
use App\Repositories\AutomationNewLeadUtmParameterRepository;
use App\Repositories\ClientyConfigWhatsAppTemplateRepository;
use App\Repositories\LeadNotificationWhatsAppMessageRepository;
use App\Repositories\TaskNotificationWhatsAppMessageRepository;
use App\Repositories\WapSalesAgentBot\WapSalesAgentBotRepository;
use App\Repositories\WhatsAppMetaAPI\WhatsAppMetaAPIConnectionRepository;

use App\Repositories\Cache\TagRepositoryCache;
use App\Repositories\Cache\UserRepositoryCache;
use App\Repositories\Cache\TaskRepositoryCache;
use App\Repositories\Cache\ClientRepositoryCache;
use App\Repositories\Cache\StatusRepositoryCache;
use App\Repositories\Cache\NPSPollRepositoryCache;
use App\Repositories\Cache\LandingRepositoryCache;
use App\Repositories\Cache\LeadSaleRepositoryCache;
use App\Repositories\Cache\NotificationRepositoryCache;
use App\Repositories\Cache\NewsNotificationRepositoryCache;
use App\Repositories\Cache\WhatsAppTemplateRepositoryCache;
use App\Repositories\Cache\UserCustomFilterRepositoryCache;
use App\Repositories\Cache\WhatsAppAttachmentRepositoryCache;
use App\Repositories\Cache\AcquisitionChannelRepositoryCache;
use App\Repositories\Cache\AutomationEmailSendRepositoryCache;
use App\Repositories\Cache\WAutomationSequenceRepositoryCache;
use App\Repositories\Cache\WhatsAppQuickResponseRepositoryCache;
use App\Repositories\Cache\GmailEmailNotificationRepositoryCache;
use App\Repositories\Cache\WAutomationSequenceStepRepositoryCache;
use App\Repositories\Cache\AutomationEmailSendStepRepositoryCache;
use App\Repositories\Cache\WapBotRepositoryCache;
use App\Repositories\Cache\WhatsAppMetaAPIConnectionRepositoryCache;

use App\Repositories\UserNotificationRepository;
use App\Repositories\Automations\AutomationLogRepository;
use App\Repositories\Automations\AutomationTaskRepository;
use App\Repositories\Automations\AutomationNewLeadRepository;
use App\Repositories\Automations\AutomationProposalRepository;
use App\Repositories\Automations\AutomationEmailSendRepository;
use App\Repositories\WAutomations\WAutomationProposalRepository;
use App\Repositories\WAutomations\WAutomationSequenceRepository;
use App\Repositories\Automations\AutomationEmailSendStepRepository;
use App\Repositories\WAutomations\WAutomationSequenceStepRepository;
use App\Repositories\Automations\AutomationProposalResendRuleRepository;
use App\Repositories\WAutomations\WAutomationProposalResendRuleRepository;
use App\Repositories\Automations\AutomationProposalInteractionRuleRepository;
use App\Repositories\Automations\AutomationProposalModifyLeadAfterSendRuleRepository;
use App\Repositories\WAutomations\WAutomationProposalModifyLeadAfterSendRuleRepository;
use App\Repositories\Automations\AutomationProposalUserNotificationOnInteractionRuleRepository;

use App\Services\API\TagService;
use App\Services\API\NewsService;
use App\Services\API\NoteService;
use App\Services\API\UserService;
use App\Services\API\AuthService;
use App\Services\API\LeadService;
use App\Services\API\TaskService;
use App\Services\API\WAPIService;
use App\Services\API\EmailService;
use App\Services\API\HealthService;
use App\Services\API\StatusService;
use App\Services\API\ClientService;
use App\Services\API\LandingService;
use App\Services\API\ManagerService;
use App\Services\API\NPSPollService;
use App\Services\API\LeadSaleService;
use App\Services\API\WAPSenderService;
use App\Services\API\AttachmentService;
use App\Services\API\LeadContactService;
use App\Services\API\TaskTemplateService;
use App\Services\API\WapBot\WapBotService;
use App\Services\API\EmailTemplateService;
use App\Services\API\NPSPollAnswerService;
use App\Services\API\StatusCategoryService;
use App\Services\API\ProposalInfoTmpService;
use App\Services\API\LeadCustomFieldService;
use App\Services\API\WhatsAppTemplateService;
use App\Services\API\WhatsAppQuickResponseService;
use App\Services\API\LeadContactPhoneService;
use App\Services\API\LeadContactEmailService;
use App\Services\API\GmailMessagesLogService;
use App\Services\API\NewsNotificationService;
use App\Services\API\AcquisitionChannelService;
use App\Services\API\GoogleAPIUserTokenService;
use App\Services\API\GoogleAPIUserContactService;
use App\Services\API\LeadCustomFieldValueService;
use App\Services\API\TaskNotificationEmailService;
use App\Services\API\LeadNotificationEmailService;
use App\Services\API\GmailEmailNotificationService;
use App\Services\API\Actions\LeadsBulkUploadService;
use App\Services\API\WapBot\WapBotConversationService;
use App\Services\API\ClientyConfigEmailTemplateService;
use App\Services\API\Notifications\NotificationService;
use App\Services\API\ClientyConfigWhatsAppTemplateService;
use App\Services\API\WapSalesAgent\WapSalesAgentBotService;
use App\Services\API\TaskNotificationWhatsAppMessageService;
use App\Services\API\LeadNotificationWhatsAppMessageService;
use App\Services\API\Actions\LeadService as ActionsLeadService;

use App\Services\API\GoogleAPI\GoogleGmailAPIService;
use App\Services\API\GoogleAPI\GoogleCommonAPIService;
use App\Services\API\GoogleAPI\GooglePeopleAPIService;

use App\Services\API\Views\Reports\UTMTraceReportService;
use App\Services\API\Views\TaskService as ViewsTaskService;
use App\Services\API\Views\LeadService as ViewsLeadService;
use App\Services\API\Views\EmailService as ViewsEmailService;
use App\Services\API\Views\Reports\SentProposalReportService;
use App\Services\API\Views\Reports\SalesHistoryReportService;
use App\Services\API\Views\StatusService as ViewsStatusService;
use App\Services\API\Views\Reports\UTMCampaignTraceReportService;
use App\Services\API\Views\Reports\AcquisitionChannelReportService;
use App\Services\API\Views\AutomationProposalService as ViewsAutomationProposalService;
use App\Services\API\Views\AutomationSequenceService as ViewsAutomationSequenceService;
use App\Services\API\Views\AutomationEmailSendService as ViewsAutomationEmailSendService;
use App\Services\API\Views\WAutomationProposalService as ViewsWAutomationProposalService;

use App\Services\API\EventsLogService;
use App\Services\API\UserLoginService;
use App\Services\API\TimelineEventsService;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;
use App\Services\API\Dispatchers\LeadEventsDispatcherService;
use App\Services\API\Dispatchers\UserEventsDispatcherService;
use App\Services\API\Dispatchers\TaskEventsDispatcherService;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\Services\API\Dispatchers\FacebookLogDispatcherService;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\Services\API\Dispatchers\SearchLeadEventsDispatcherService;
use App\Services\API\Dispatchers\ElasticLeadEventsDispatcherService;
use App\Services\API\Dispatchers\GoogleContactsEventsDispatcherService;
use App\Services\API\Dispatchers\IntegrationAPIEventsDispatcherService;
use App\Services\API\Dispatchers\EmailValidationEventsDispatcherService;
use App\Services\API\Dispatchers\WhatsAppNotificationEventsDispatcherService;

use App\Services\API\Automations\AutomationLogService;
use App\Services\API\Automations\AutomationTaskService;
use App\Services\API\WAutomations\WAutomationLogService;
use App\Services\API\Automations\AutomationNewLeadService;
use App\Services\API\Automations\AutomationEmailSendService;
use App\Services\API\Automations\AutomationFlowChartService;
use App\Services\API\WAutomations\WAutomationSequenceService;
use App\Services\API\Automations\AutomationEmailSendStepService;
use App\Services\API\WAutomations\WAutomationSequenceStepService;
use App\Services\API\Automations\AutomationProposalResendService;
use App\Services\API\WAutomations\WAutomationProposalResendService;
use App\Services\API\Automations\AutomationNewLeadFormFieldService;
use App\Services\API\Automations\AutomationProposalInteractionService;
use App\Services\API\Automations\AutomationNewLeadUtmParameterService;
use App\Services\API\Automations\AutomationNewLeadCustomFieldMatchService;
use App\Services\API\Automations\AutomationNewLeadTrackingParameterService;
use App\Services\API\Automations\AutomationNewLeadCustomFieldMappingService;
use App\Services\API\Automations\AutomationProposalModifyLeadAfterSendService;
use App\Services\API\WAutomations\WAutomationProposalModifyLeadAfterSendService;

use App\Services\API\ProposalInfoService;
use App\Services\API\ClientSettingsService;
use App\Services\API\WhatsAppSendingService;
use App\Services\API\UserCustomFilterService;
use App\Services\API\UserNotificationService;
use App\Services\API\Import\ImportLeadService;
use App\Services\API\WhatsAppAttachmentService;
use App\Services\API\ClientFacebookPageService;
use App\Services\API\Import\ImportClientService;
use App\Services\Validators\EmailServiceValidator;
use App\Services\API\WhatsAppSendingMessageService;
use App\Services\API\WhatsAppSendingMessageTextService;
use App\Services\API\Automations\AutomationProposalService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\WAutomations\WAutomationProposalService;


class AppServiceProvider extends ServiceProvider
{

    public function register()
    {
        if (!config('app.enableDebugBar')) {
            \Debugbar::disable();
        }


        // ----------------
        // - Repositories
        // --------------------
        // @todo: mejorar/encapsular/refactorizar esto (algun factory?)
        $this->app->singleton(LeadRepository::class, function () {
            return new LeadRepository(
                resolve(MongoSearchHelper::class)
            );
        });

        $this->app->singleton(TagRepository::class, function () {
            $repo = new TagRepository();
            return config('app.enable_redis_cache') ? new TagRepositoryCache($repo) : $repo;
        });

        $this->app->singleton(TaskRepository::class, function () {
            $repo = new TaskRepository(resolve(MongoSearchHelper::class));
            return config('app.enable_redis_cache') ? new TaskRepositoryCache($repo) : $repo;
        });

        $this->app->singleton(UserRepository::class, function () {
            $repo = new UserRepository();
            return config('app.enable_redis_cache') ? new UserRepositoryCache($repo) : $repo;
        });

        $this->app->singleton(ClientRepository::class, function () {
            $repo = new ClientRepository();
            return config('app.enable_redis_cache') ? new ClientRepositoryCache($repo) : $repo;
        });

        $this->app->singleton(NotificationRepository::class, function () {
            $repo = new NotificationRepository();
            return config('app.enable_redis_cache') ? new NotificationRepositoryCache($repo) : $repo;
        });

        $this->app->singleton(NewsNotificationRepository::class, function () {
            $repo = new NewsNotificationRepository();
            return config('app.enable_redis_cache') ? new NewsNotificationRepositoryCache($repo) : $repo;
        });

        $this->app->singleton(AutomationEmailSendRepository::class, function () {
            $repo = new AutomationEmailSendRepository();
            return config('app.enable_redis_cache') ? new AutomationEmailSendRepositoryCache($repo) : $repo;
        });

        $this->app->singleton(GmailEmailNotificationRepository::class, function () {
            $repo = new GmailEmailNotificationRepository();
            return config('app.enable_redis_cache') ? new GmailEmailNotificationRepositoryCache($repo) : $repo;
        });
        
        $this->app->singleton(AutomationProposalRepository::class, function () {
            return new AutomationProposalRepository();
        });
        
        $this->app->singleton(WAutomationProposalRepository::class, function () {
            return new WAutomationProposalRepository();
        });
        
        $this->app->singleton(AutomationNewLeadRepository::class, function () {
            return new AutomationNewLeadRepository();
        });
        
        $this->app->singleton(WapBotRepository::class, function () {
            $repo = new WapBotRepository();
            return config('app.enable_redis_cache') ? new WapBotRepositoryCache($repo) : $repo;
        });

        $this->app->singleton(WapSalesAgentBotRepository::class, function () {
            return new WapSalesAgentBotRepository();
        });
        
        $this->app->singleton(WapBotConversationRepository::class, function () {
            return new WapBotConversationRepository();
        });

        $this->app->singleton(WhatsAppMetaAPIConnectionRepository::class, function () {
            $repo = new WhatsAppMetaAPIConnectionRepository();
            return config('app.enable_redis_cache') ? new WhatsAppMetaAPIConnectionRepositoryCache($repo) : $repo;
        });

        // ----------------
        // - /Repositories
        // --------------------


        $this->app->singleton(ViewsLeadService::class, function () {
            return new ViewsLeadService(
                leadRepository: resolve(LeadRepository::class),
                clientEventsDispatcherService: resolve(ClientEventsDispatcherService::class),
            );
        });

        $this->app->singleton(ViewsTaskService::class, function () {
            return new ViewsTaskService(resolve(TaskRepository::class));
        });

        $this->app->singleton(NotificationService::class, function () {
            return new NotificationService(resolve(NotificationRepository::class));
        });

        $this->app->singleton(GmailEmailNotificationService::class, function () {
            return new GmailEmailNotificationService(resolve(GmailEmailNotificationRepository::class));
        });

        $this->app->singleton(NewsNotificationService::class, function () {
            return new NewsNotificationService(
                resolve(NewsNotificationRepository::class),
                resolve(UserService::class),
                resolve(ClientService::class),
            );
        });

        $this->app->singleton(NPSPollService::class, function (Application $app, array $params) {
            $repo = new NPSPollRepository();
            if (config('app.enable_redis_cache')) {
                $repo = new NPSPollRepositoryCache($repo);
            }
            return new NPSPollService(
                NPSPollRepository: $repo,
                NPSPollAnswerService: resolve(NPSPollAnswerService::class),
            );
        });

        $this->app->singleton(NPSPollAnswerService::class, function () {
            return new NPSPollAnswerService(
                resolve(UserService::class),
                resolve(ClientService::class),
                resolve(NPSPollAnswerRepository::class),
            );
        });

        $this->app->singleton(ViewsEmailService::class, function () {
            return new ViewsEmailService(
                emailRepository: resolve(EmailRepository::class),
                attachmentService: resolve(AttachmentService::class),
                mongoSearchHelper: resolve(MongoSearchHelper::class),
                clientyMailerAPIHelper: resolve(ClientyMailerAPIHelper::class),
                leadContactEmailService: resolve(LeadContactEmailService::class),
                emailNotificationLogRepository: resolve(EmailNotificationLogRepository::class),
            );
        });

        $this->app->singleton(WAPIService::class, function () {
            return new WAPIService(
                WAPIHelper: resolve(WAPIHelper::class),
                userService: resolve(UserService::class),
                phonesHelper: resolve(PhonesHelper::class),
                whatsAppSendingService: resolve(WhatsAppSendingService::class),
                proposalInfoTmpService: resolve(ProposalInfoTmpService::class),
                leadContactPhoneService: resolve(LeadContactPhoneService::class),
                userEventsDispatcherService: resolve(UserEventsDispatcherService::class),
                whatsAppEventsDispatcherService: resolve(WhatsAppEventsDispatcherService::class),
                timelineEventsDispatcherService: resolve(TimelineEventsDispatcherService::class),
            );
        });

        $this->app->singleton(WAPSenderService::class, function () {
            return new WAPSenderService(
                phonesHelper: resolve(PhonesHelper::class),
                whatsAppSendingService: resolve(WhatsAppSendingService::class),
                proposalInfoTmpService: resolve(ProposalInfoTmpService::class),
                leadContactPhoneService: resolve(LeadContactPhoneService::class),
                whatsAppSendingMessageService: resolve(WhatsAppSendingMessageService::class),
                whatsAppEventsDispatcherService: resolve(WhatsAppEventsDispatcherService::class),
                timelineEventsDispatcherService: resolve(TimelineEventsDispatcherService::class),
            );
        });

        $this->app->singleton(ViewsStatusService::class, function () {
            return new ViewsStatusService(
                resolve(EventsLogService::class)
            );
        });

        $this->app->singleton(ViewsAutomationProposalService::class, function () {
            return new ViewsAutomationProposalService(
                resolve(AutomationProposalRepository::class),
                resolve(TagService::class),
            );
        });

        $this->app->singleton(ViewsWAutomationProposalService::class, function () {
            return new ViewsWAutomationProposalService(
                resolve(WAutomationProposalRepository::class),
                resolve(TagService::class)
            );
        });

        $this->app->singleton(EmailValidatorHelper::class, function () {
            return new EmailValidatorHelper();
        });

        $this->app->singleton(ViewsAutomationEmailSendService::class, function () {
            return new ViewsAutomationEmailSendService(
                mermaidChartHelper: resolve(MermaidChartHelper::class),
                automationEmailSendRepository: resolve(AutomationEmailSendRepository::class)
            );
        });

        $this->app->singleton(ActionsLeadService::class, function () {
            $actionsLeadService = new ActionsLeadService(
                resolve(LeadRepository::class),
                resolve(TagService::class),
                resolve(LeadEventsDispatcherService::class),
                resolve(ClientEventsDispatcherService::class),
                resolve(TimelineEventsDispatcherService::class),
                resolve(GoogleContactsEventsDispatcherService::class),
                resolve(IntegrationAPIEventsDispatcherService::class)
            );
            $actionsLeadService->setLeadNotificationEmailService(
                LeadNotificationEmailService::getExistentInstance() ?? resolve(LeadNotificationEmailService::class)
            );
            return $actionsLeadService;
        });


        $this->app->singleton(AuthService::class, function () {
            return new AuthService(
                resolve(UserService::class),
                config('auth.jwt.secret'),
                config('auth.jwt.algo'),
            );
        });

        $this->app->singleton(ClientSettingsService::class, function () {
            return new ClientSettingsService(
                clientSettingsRepository: new ClientSettingsRepository(),
                clientEventsDispatcherService: resolve(ClientEventsDispatcherService::class),
            );
        });

        $this->app->singleton(TaskService::class, function () {
            return new TaskService(
                taskRepository: resolve(TaskRepository::class),
                browserEventsDispatcher: resolve(BrowserEventsDispatcher::class),
                taskEventsDispatcherService: resolve(TaskEventsDispatcherService::class),
                taskNotificationEmailService: resolve(TaskNotificationEmailService::class),
                clientEventsDispatcherService: resolve(ClientEventsDispatcherService::class),
                timelineEventsDispatcherService: resolve(TimelineEventsDispatcherService::class),
                integrationAPIEventsDispatcherService: resolve(IntegrationAPIEventsDispatcherService::class),
                taskNotificationWhatsAppMessageService: resolve(TaskNotificationWhatsAppMessageService::class),
            );
        });

        $this->app->singleton(LeadSaleService::class, function () {
            $repo = new LeadSaleRepository();
            if (config('app.enable_redis_cache')) {
                $repo = new LeadSaleRepositoryCache($repo);
            }
            return new LeadSaleService(
                $repo,
                resolve(TimelineEventsDispatcherService::class),
                resolve(IntegrationAPIEventsDispatcherService::class),
            );
        });

        $this->app->singleton(SentProposalReportService::class, function () {
            return new SentProposalReportService(
                new ProposalInfoRepository(),
                resolve(ViewsLeadService::class)
            );
        });

        $this->app->singleton(SalesHistoryReportService::class, function () {
            $repo = new LeadSaleRepository();
            if (config('app.enable_redis_cache')) {
                $repo = new LeadSaleRepositoryCache($repo);
            }
            return new SalesHistoryReportService(
                $repo,
                resolve(ViewsLeadService::class)
            );
        });

        $this->app->singleton(AcquisitionChannelReportService::class, function () {
            return new AcquisitionChannelReportService(
                resolve(AcquisitionChannelService::class),
                resolve(ViewsLeadService::class)
            );
        });

        $this->app->singleton(UTMTraceReportService::class, function () {
            return new UTMTraceReportService(
                resolve(ViewsLeadService::class)
            );
        });

        $this->app->singleton(UTMCampaignTraceReportService::class, function () {
            return new UTMCampaignTraceReportService(
                resolve(AcquisitionChannelService::class)
            );
        });

        $this->app->singleton(ProposalInfoService::class, function () {
            return new ProposalInfoService(
                new ProposalInfoRepository()
            );
        });

        $this->app->singleton(ProposalInfoTmpRepository::class, function () {
            return new ProposalInfoTmpRepository(
                new ProposalInfoTmpRepository()
            );
        });

        $this->app->singleton(LandingService::class, function (Application $app, array $params) {
            $repo = new LandingRepository();
            if (config('app.enable_redis_cache')) {
                $repo = new LandingRepositoryCache($repo);
            }
            return new LandingService($repo);
        });

        $this->app->singleton(AcquisitionChannelService::class, function (Application $app, array $params) {
            $repo = new AcquisitionChannelRepository();
            if (config('app.enable_redis_cache')) {
                $repo = new AcquisitionChannelRepositoryCache($repo);
            }
            return new AcquisitionChannelService($repo);
        });

        $this->app->singleton(EmailTemplateService::class, function () {
            return new EmailTemplateService(
                new EmailTemplateRepository(),
                resolve(BrowserEventsDispatcher::class)
            );
        });

        $this->app->singleton(ClientyConfigEmailTemplateService::class, function () {
            return new ClientyConfigEmailTemplateService(
                new ClientyConfigEmailTemplateRepository()
            );
        });

        $this->app->singleton(ClientyConfigWhatsAppTemplateService::class, function () {
            return new ClientyConfigWhatsAppTemplateService(
                new ClientyConfigWhatsAppTemplateRepository()
            );
        });

        $this->app->singleton(LeadContactService::class, function () {
            return new LeadContactService(
                new LeadContactRepository(),
                resolve(LeadContactEmailService::class),
                resolve(LeadContactPhoneService::class),
                resolve(SearchLeadEventsDispatcherService::class),
                resolve(GoogleContactsEventsDispatcherService::class)
            );
        });

        $this->app->singleton(LeadContactEmailService::class, function () {
            return new LeadContactEmailService(
                new LeadContactEmailRepository(),
                resolve(LeadEventsDispatcherService::class),
                resolve(SearchLeadEventsDispatcherService::class),
                resolve(EmailValidationEventsDispatcherService::class),
                resolve(GoogleContactsEventsDispatcherService::class),
                resolve(ClientEventsDispatcherService::class),
            );
        });

        $this->app->singleton(LeadContactPhoneService::class, function () {
            return new LeadContactPhoneService(
                leadContactPhoneRepository: new LeadContactPhoneRepository(),
                leadEventsDispatcherService: resolve(LeadEventsDispatcherService::class),
                searchLeadEventsDispatcherService: resolve(SearchLeadEventsDispatcherService::class),
                googleContactsEventsDispatcherService: resolve(GoogleContactsEventsDispatcherService::class),
            );
        });

        $this->app->singleton(StatusService::class, function (Application $app, array $params) {
            $repo = new StatusRepository();
            if (config('app.enable_redis_cache')) {
                $repo = new StatusRepositoryCache($repo);
            }
            return new StatusService(
                statusRepository: $repo,
                statusCategoryService: resolve(StatusCategoryService::class),
            );
        });

        $this->app->singleton(NoteService::class, function () {
            $noteRepository = new NoteRepository();
            return new NoteService(
                noteRepository: $noteRepository,
                clientEventsDispatcherService: resolve(ClientEventsDispatcherService::class),
                timelineEventsDispatcherService: resolve(TimelineEventsDispatcherService::class),
            );
        });

        $this->app->singleton(TagService::class, function (Application $app, array $params) {
            return new TagService(resolve(TagRepository::class));
        });

        $this->app->singleton(AttachmentService::class, function () {
            return new AttachmentService(
                new AttachmentRepository(),
                resolve(ClientyMailerAPIHelper::class)
            );
        });

        $this->app->singleton(UserService::class, function (Application $app, array $params) {
            $userService = new UserService(
                WAPIHelper: resolve(WAPIHelper::class),
                userRepository: resolve(UserRepository::class),
                automationLogService: resolve(AutomationLogService::class),
                clientyMailerAPIHelper: resolve(ClientyMailerAPIHelper::class),
                emailEventsDispatcherService: resolve(EmailEventsDispatcherService::class),
                timelineEventsDispatcherService: resolve(TimelineEventsDispatcherService::class),
            );
            $userService->setLeadService(
                LeadService::getExistentInstance() ?? resolve(LeadService::class)
            );
            return $userService;
        });

        $this->app->singleton(UserCustomFilterService::class, function () {
            $repo = new UserCustomFilterRepository();
            if (config('app.enable_redis_cache')) {
                $repo = new UserCustomFilterRepositoryCache($repo);
            }
            return new UserCustomFilterService($repo);
        });

        $this->app->singleton(WhatsAppTemplateService::class, function () {
            $repo = new WhatsAppTemplateRepository();
            if (config('app.enable_redis_cache')) {
                $repo = new WhatsAppTemplateRepositoryCache($repo);
            }
            return new WhatsAppTemplateService(
                whatsAppTemplateRepository: $repo,
                whatsAppMetaAPIHelper: resolve(WhatsAppMetaAPIHelper::class),
                whatsAppAttachmentService: resolve(WhatsAppAttachmentService::class),
                whatsAppEventsDispatcherService: resolve(WhatsAppEventsDispatcherService::class),
            );
        });

        $this->app->singleton(WhatsAppQuickResponseService::class, function () {
            $repo = new WhatsAppQuickResponseRepository();
            if (config('app.enable_redis_cache')) {
                $repo = new WhatsAppQuickResponseRepositoryCache($repo);
            }
            return new WhatsAppQuickResponseService(
                whatsAppQuickResponseRepository: $repo,
            );
        });

        $this->app->singleton(WhatsAppAttachmentService::class, function () {
            $repo = new WhatsAppAttachmentRepository();
            // if (config('app.enable_redis_cache')) {
            //     $repo = new WhatsAppAttachmentRepositoryCache($repo);
            // }
            return new WhatsAppAttachmentService(
                whatsAppAttachmentRepository: $repo,
                whatsAppMetaAPIHelper: resolve(WhatsAppMetaAPIHelper::class),
                whatsAppAttachmentHelper: resolve(WhatsAppAttachmentHelper::class)
            );
        });

        $this->app->singleton(TaskTemplateService::class, function () {
            return new TaskTemplateService(
                new TaskTemplateRepository()
            );
        });

        $this->app->singleton(GoogleAPIUserTokenService::class, function () {
            return new GoogleAPIUserTokenService(
                resolve(GoogleAPIUserTokenRepository::class),
            );
        });

        $this->app->singleton(GoogleAPIUserContactService::class, function () {
            return new GoogleAPIUserContactService(
                resolve(GoogleAPIUserContactRepository::class),
                resolve(GooglePeopleAPIService::class),
            );
        });

        $this->app->singleton(GoogleCommonAPIService::class, function () {
            return new GoogleCommonAPIService(
                resolve(GoogleAPIHelper::class),
                resolve(GoogleAPIUserTokenService::class),
                config('google_api.test_addresses')
            );
        });

        $this->app->singleton(GooglePeopleAPIService::class, function () {
            return new GooglePeopleAPIService(
                resolve(GoogleCommonAPIService::class)
            );
        });

        $this->app->singleton(GoogleGmailAPIService::class, function () {
            return new GoogleGmailAPIService(
                resolve(GoogleCommonAPIService::class),
                resolve(GoogleGmailAPIHelper::class)
            );
        });

        $this->app->singleton(EventsLogService::class, function () {
            return new EventsLogService(
                resolve(EventsLogRepository::class)
            );
        });

        $this->app->singleton(
            TimelineEventsDispatcherService::class,
            function (Application $app, array $params) {
                $user = $params['user'] ?? null;
                return new TimelineEventsDispatcherService(
                    config('queue.timeline_events'),
                    config('queue.default'),
                    $user
                );
            }
        );

        $this->app->singleton(BrowserEventsDispatcher::class, function () {
            return new BrowserEventsDispatcher();
        });

        $this->app->singleton(
            RedisHelper::class,
            function (Application $app, array $params) {
                $clientId = $params['clientId'] ?? null;
                $connectionName = $params['connectionName'] ?? null;

                if (!$connectionName) {
                    return new RedisHelper($clientId);
                }
                return new RedisHelper($clientId, $connectionName);
            }
        );

        $this->app->singleton(EmailEventsDispatcherService::class, function () {
            return new EmailEventsDispatcherService(
                config('queue.email_events'), config('queue.default')
            );
        });

        $this->app->singleton(LeadEventsDispatcherService::class, function () {
            return new LeadEventsDispatcherService(
                config('queue.lead_events'), config('queue.default')
            );
        });

        $this->app->singleton(TaskEventsDispatcherService::class, function () {
            return new TaskEventsDispatcherService(
                config('queue.task_events'), config('queue.default')
            );
        });

        $this->app->singleton(UserEventsDispatcherService::class, function () {
            return new UserEventsDispatcherService(
                config('queue.user_events'), config('queue.default')
            );
        });

        $this->app->singleton(SearchLeadEventsDispatcherService::class, function () {
            return new SearchLeadEventsDispatcherService(
                config('queue.lead_search_events'), config('queue.default')
            );
        });

        $this->app->singleton(ElasticLeadEventsDispatcherService::class, function () {
            return new ElasticLeadEventsDispatcherService(
                config('queue.elastic_lead_events'), config('queue.default')
            );
        });

        $this->app->singleton(GoogleContactsEventsDispatcherService::class, function () {
            return new GoogleContactsEventsDispatcherService(
                config('queue.google_contacts_events'), config('queue.default')
            );
        });

        $this->app->singleton(EmailValidationEventsDispatcherService::class, function () {
            return new EmailValidationEventsDispatcherService(
                config('queue.email_validation_events'), config('queue.default')
            );
        });

        $this->app->singleton(ClientEventsDispatcherService::class, function () {
            return new ClientEventsDispatcherService(
                config('queue.client_events'), config('queue.default')
            );
        });

        $this->app->singleton(FacebookLogDispatcherService::class, function () {
            return new FacebookLogDispatcherService(
                config('queue.facebook_events'), config('queue.default')
            );
        });

        $this->app->singleton(IntegrationAPIEventsDispatcherService::class, function () {
            return new IntegrationAPIEventsDispatcherService(
                config('queue.integration_api_events'), config('queue.default')
            );
        });

        $this->app->singleton(WhatsAppEventsDispatcherService::class, function () {
            return new WhatsAppEventsDispatcherService(
                config('queue.whatsapp_events'), config('queue.default')
            );
        });

        $this->app->singleton(WhatsAppNotificationEventsDispatcherService::class, function () {
            return new WhatsAppNotificationEventsDispatcherService(
                config('queue.whatsapp_notification_events'), config('queue.default')
            );
        });


        $this->app->singleton(TimelineEventsService::class, function () {
            return new TimelineEventsService(
                resolve(ViewsEmailService::class),
                resolve(EventsLogService::class),
                resolve(GmailMessagesLogService::class),
                config('app.timeline_events')
            );
        });

        $this->app->singleton(UserLoginService::class, function () {
            return new UserLoginService(new UserLoginRepository());
        });

        $this->app->singleton(EmailService::class, function () {
            return new EmailService(
                resolve(EmailRepository::class),
                resolve(EmailServiceValidator::class),
                resolve(ClientyMailerAPIHelper::class),
                resolve(EmailEventsDispatcherService::class),
                resolve(ClientEventsDispatcherService::class),
                resolve(TimelineEventsDispatcherService::class),
                config('app.clienty_mailer.system_email_from'),
                config('app.clienty_mailer.system_name_from')
            );
        });

        $this->app->singleton(LeadCustomFieldService::class, function () {
            return new LeadCustomFieldService(
                resolve(LeadCustomFieldRepository::class),
                resolve(LeadCustomFieldValueService::class),
                resolve(SearchLeadEventsDispatcherService::class),
            );
        });

        $this->app->singleton(LeadService::class, function () {
            $leadService = new LeadService(
                resolve(LeadRepository::class),
                resolve(LeadContactService::class),
                resolve(LandingService::class),
                resolve(NoteService::class),
                resolve(StatusService::class),
                resolve(AcquisitionChannelService::class),
                resolve(LeadCustomFieldService::class),
                resolve(BrowserEventsDispatcher::class),
                resolve(LeadContactEmailService::class),
                resolve(LeadContactPhoneService::class),
                resolve(LeadEventsDispatcherService::class),
                resolve(TimelineEventsDispatcherService::class),
                resolve(SearchLeadEventsDispatcherService::class),
                resolve(GoogleContactsEventsDispatcherService::class),
                resolve(IntegrationAPIEventsDispatcherService::class),
            );
            $leadService->setLeadNotificationEmailService(
                LeadNotificationEmailService::getExistentInstance() ?? resolve(LeadNotificationEmailService::class)
            );
            $leadService->setLeadNotificationWhatsAppMessageService(
                LeadNotificationWhatsAppMessageService::getExistentInstance() ??
                resolve(LeadNotificationWhatsAppMessageService::class)
            );
            $leadService->setUserService(
                UserService::getExistentInstance() ?? resolve(UserService::class)
            );
            $leadService->setAutomationNewLeadService(
                AutomationNewLeadService::getExistentInstance() ?? resolve(AutomationNewLeadService::class)
            );
            return $leadService;
        });

        $this->app->singleton(AutomationNewLeadService::class, function () {
            $autService = new AutomationNewLeadService(
                userService: resolve(UserService::class),
                taskService: resolve(TaskService::class),
                noteService: resolve(NoteService::class),
                emailService: resolve(EmailService::class),
                mermaidChartHelper: resolve(MermaidChartHelper::class),
                notificationService: resolve(NotificationService::class),
                automationLogService: resolve(AutomationLogService::class),
                leadCustomFieldService: resolve(LeadCustomFieldService::class),
                leadContactEmailService: resolve(LeadContactEmailService::class),
                leadContactPhoneService: resolve(LeadContactPhoneService::class),
                automationNewLeadRepository: resolve(AutomationNewLeadRepository::class),
                leadNotificationEmailService: resolve(LeadNotificationEmailService::class),
                emailEventsDispatcherService: resolve(EmailEventsDispatcherService::class),
                automationNewLeadFormFieldService: resolve(AutomationNewLeadFormFieldService::class),
                automationNewLeadUtmParameterService: resolve(AutomationNewLeadUtmParameterService::class),
                leadNotificationWhatsAppMessageService: resolve(LeadNotificationWhatsAppMessageService::class),
                automationNewLeadCustomFieldMatchService: resolve(AutomationNewLeadCustomFieldMatchService::class),
                automationNewLeadTrackingParameterService: resolve(AutomationNewLeadTrackingParameterService::class),
                automationNewLeadCustomFieldMappingService: resolve(AutomationNewLeadCustomFieldMappingService::class),
            );
            $autService->setLeadService(
                LeadService::getExistentInstance() ?? resolve(LeadService::class)
            );
            $autService->setActionsLeadService(
                ActionsLeadService::getExistentInstance() ?? resolve(ActionsLeadService::class)
            );
            return $autService;
        });

        $this->app->singleton(AutomationLogService::class, function () {
            return new AutomationLogService(new AutomationLogRepository());
        });

        $this->app->singleton(AutomationEmailSendService::class, function () {
            return new AutomationEmailSendService(
                userService: resolve(UserService::class),
                leadService: resolve(LeadService::class),
                emailService: resolve(EmailService::class),
                statusService: resolve(StatusService::class),
                leadSaleService: resolve(LeadSaleService::class),
                eventsLogService: resolve(EventsLogService::class),
                notificationService: resolve(NotificationService::class),
                automationLogService: resolve(AutomationLogService::class),
                emailEventsDispatcherService: resolve(EmailEventsDispatcherService::class),
                automationEmailSendRepository: resolve(AutomationEmailSendRepository::class),
                automationEmailSendStepService: resolve(AutomationEmailSendStepService::class),
            );
        });

        $this->app->singleton(AutomationTaskService::class, function () {
            return new AutomationTaskService(
                taskService: resolve(TaskService::class),
                leadService: resolve(LeadService::class),
                leadSaleService: resolve(LeadSaleService::class),
                eventsLogService: resolve(EventsLogService::class),
                mermaidChartHelper: resolve(MermaidChartHelper::class),
                actionsLeadService: resolve(ActionsLeadService::class),
                taskTemplateService: resolve(TaskTemplateService::class),
                automationTaskRepository: new AutomationTaskRepository(),
                automationLogService: resolve(AutomationLogService::class),
                emailEventsDispatcherService: resolve(EmailEventsDispatcherService::class),
            );
        });

        $this->app->singleton(AutomationEmailSendStepService::class, function () {
            $repo = resolve(AutomationEmailSendStepRepository::class);
            if (config('app.enable_redis_cache')) {
                $repo = new AutomationEmailSendStepRepositoryCache($repo);
            }
            return new AutomationEmailSendStepService(
                $repo,
                resolve(AutomationLogService::class)
            );
        });

        $this->app->singleton(WAutomationSequenceService::class, function () {
            $wAutomationSequenceRepo = new WAutomationSequenceRepository();
            if (config('app.enable_redis_cache')) {
                $wAutomationSequenceRepo = new WAutomationSequenceRepositoryCache($wAutomationSequenceRepo);
            }

            return new WAutomationSequenceService(
                WAPIService: resolve(WAPIService::class),
                userService: resolve(UserService::class),
                leadService: resolve(LeadService::class),
                statusService: resolve(StatusService::class),
                leadSaleService: resolve(LeadSaleService::class),
                eventsLogService: resolve(EventsLogService::class),
                WAPSenderService: resolve(WAPSenderService::class),
                mermaidChartHelper: resolve(MermaidChartHelper::class),
                wAutomationSequenceRepository: $wAutomationSequenceRepo,
                notificationService: resolve(NotificationService::class),
                wAutomationLogService: resolve(WAutomationLogService::class),
                whatsAppMetaAPIService: resolve(WhatsAppMetaAPIService::class),
                whatsAppSendingService: resolve(WhatsAppSendingService::class),
                whatsAppTemplateService: resolve(WhatsAppTemplateService::class),
                emailEventsDispatcherService: resolve(EmailEventsDispatcherService::class),
                wAutomationSequenceStepService: resolve(WAutomationSequenceStepService::class),
            );
        });

        $this->app->singleton(WAutomationSequenceStepService::class, function () {
            $wAutSequenceStepRepo = new WAutomationSequenceStepRepository();
            if (config('app.enable_redis_cache')) {
                $wAutSequenceStepRepo = new WAutomationSequenceStepRepositoryCache($wAutSequenceStepRepo);
            }
            return new WAutomationSequenceStepService(
                wAutomationSequenceStepRepository: $wAutSequenceStepRepo,
                wAutomationLogService: resolve(WAutomationLogService::class),
            );
        });

        $this->app->singleton(AutomationProposalService::class, function () {
            $afterSendService = resolve(AutomationProposalModifyLeadAfterSendService::class);
            
            return new AutomationProposalService(
                tagService: resolve(TagService::class),
                mermaidChartHelper: resolve(MermaidChartHelper::class),
                emailTemplateService: resolve(EmailTemplateService::class),
                automationProposalModifyLeadAfterSendService: $afterSendService,
                automationProposalRepository: resolve(AutomationProposalRepository::class),
                emailEventsDispatcherService: resolve(EmailEventsDispatcherService::class),
                automationProposalResendService: resolve(AutomationProposalResendService::class),
                automationProposalInteractionService: resolve(AutomationProposalInteractionService::class),
            );
        });

        $this->app->singleton(WAutomationProposalService::class, function () {
            $afterSendService = resolve(WAutomationProposalModifyLeadAfterSendService::class);

            return new WAutomationProposalService(
                tagService: resolve(TagService::class),
                mermaidChartHelper: resolve(MermaidChartHelper::class),
                whatsAppTemplateService: resolve(WhatsAppTemplateService::class),
                wAutomationProposalModifyLeadAfterSendService: $afterSendService,
                emailEventsDispatcherService: resolve(EmailEventsDispatcherService::class),
                wAutomationProposalRepository: resolve(WAutomationProposalRepository::class),
                wAutomationProposalResendService: resolve(WAutomationProposalResendService::class),
            );
        });

        $this->app->singleton(AutomationProposalModifyLeadAfterSendService::class, function () {
            return new AutomationProposalModifyLeadAfterSendService(
                new AutomationProposalModifyLeadAfterSendRuleRepository(),
                resolve(ActionsLeadService::class),
                resolve(TagService::class),
                resolve(AutomationLogService::class)
            );
        });

        $this->app->singleton(WAutomationProposalModifyLeadAfterSendService::class, function () {
            return new WAutomationProposalModifyLeadAfterSendService(
                new WAutomationProposalModifyLeadAfterSendRuleRepository(),
                resolve(ActionsLeadService::class),
                resolve(TagService::class),
                resolve(WAutomationLogService::class)
            );
        });

        $this->app->singleton(AutomationProposalResendService::class, function () {
            return new AutomationProposalResendService(
                new AutomationProposalResendRuleRepository(),
                resolve(AutomationLogService::class),
                resolve(UserService::class),
                resolve(EmailService::class),
                resolve(AttachmentService::class),
                resolve(NotificationService::class),
                (int) config('app.automation.proposal_resend.limit_window_hours'),
            );
        });

        $this->app->singleton(WAutomationProposalResendService::class, function () {
            return new WAutomationProposalResendService(
                wAutomationProposalResendRuleRepository: new WAutomationProposalResendRuleRepository(),
                WAPIService: resolve(WAPIService::class),
                WAPSenderService: resolve(WAPSenderService::class),
                notificationService: resolve(NotificationService::class),
                wAutomationLogService: resolve(WAutomationLogService::class),
                whatsAppMetaAPIService: resolve(WhatsAppMetaAPIService::class),
                whatsAppSendingService: resolve(WhatsAppSendingService::class),
                whatsAppTemplateService: resolve(WhatsAppTemplateService::class),
                windowHoursLimit: (int) config('app.automation.wapi_send.limit_window_hours'),
                whatsAppSendingMessageService: resolve(WhatsAppSendingMessageService::class),
            );
        });

        $this->app->singleton(AutomationProposalInteractionService::class, function () {
            return new AutomationProposalInteractionService(
                new AutomationProposalInteractionRuleRepository(),
                resolve(ActionsLeadService::class),
                resolve(TaskService::class),
                resolve(TagService::class),
                resolve(EmailService::class),
                resolve(AutomationUserNotificationHelper::class),
                resolve(AutomationLogService::class)
            );
        });

        $this->app->singleton(ClientFacebookPageService::class, function () {
            return new ClientFacebookPageService(
                new ClientFacebookPageRepository(),
                resolve(FacebookPageHelper::class),
                resolve(FacebookAdHelper::class),
                resolve(LeadService::class),
                resolve(FacebookLogDispatcherService::class),
                config('app.facebook.redirect_url')
            );
        });

        $this->app->singleton(WhatsAppMetaAPIService::class, function () {
            return new WhatsAppMetaAPIService(
                whatsAppMetaAPIHelper: resolve(WhatsAppMetaAPIHelper::class),
                whatsAppSendingService: resolve(WhatsAppSendingService::class),
                proposalInfoTmpService: resolve(ProposalInfoTmpService::class),
                leadContactPhoneService: resolve(LeadContactPhoneService::class),
                whatsAppSendingMessageService: resolve(WhatsAppSendingMessageService::class),
                whatsAppEventsDispatcherService: resolve(WhatsAppEventsDispatcherService::class),
                timelineEventsDispatcherService: resolve(TimelineEventsDispatcherService::class),
                whatsAppMetaAPIConnectionRepository: resolve(WhatsAppMetaAPIConnectionRepository::class),
            );
        });

        $this->app->singleton(ClientService::class, function () {
            return new ClientService(
                resolve(ClientRepository::class),
                resolve(ClientSettingsService::class),
            );
        });

        $this->app->singleton(ManagerService::class, function () {
            return new ManagerService(
                resolve(ManagerRepository::class)
            );
        });

        $this->app->singleton(ImportClientService::class, function () {
            return new ImportClientService(
                leadsAPIEndpoint: config('services.leads.api_endpoint'),
                leadsAPISecret: config('services.leads.secret'),
                clientService: resolve(ClientService::class),
                userService: resolve(UserService::class),
                managerService: resolve(ManagerService::class),
                landingService: resolve(LandingService::class),
                statusService: resolve(StatusService::class),
                newsService: resolve(NewsService::class),
                acquisitionChannelService: resolve(AcquisitionChannelService::class),
                automationProposalService: resolve(AutomationProposalService::class),
                automationEmailSendService: resolve(AutomationEmailSendService::class),
                wAutomationProposalService: resolve(WAutomationProposalService::class),
                wAutomationSequenceService: resolve(WAutomationSequenceService::class),
                clientEventsDispatcherService: resolve(ClientEventsDispatcherService::class),
            );
        });

        $this->app->singleton(ImportLeadService::class, function () {
            return new ImportLeadService(
                config('services.leads.api_endpoint'),
                config('services.leads.secret'),
                resolve(LeadService::class),
                resolve(ClientService::class)
            );
        });

        $this->app->singleton(LeadNotificationEmailService::class, function () {
            $service = new LeadNotificationEmailService(
                resolve(LeadNotificationEmailRepository::class),
                resolve(ClientyMailerAPIHelper::class),
                config('emails.notifications_email_enabled'),
                config('emails.leads_notification_from_email'),
                config('emails.leads_notification_bcc_email')
            );
            $service->setLeadService(
                LeadService::getExistentInstance() ?? resolve(LeadService::class)
            );
            return $service;
        });

        $this->app->singleton(LeadNotificationWhatsAppMessageService::class, function () {
            $wapNotificationEventsDispatcherService = resolve(WhatsAppNotificationEventsDispatcherService::class);

            $service = new LeadNotificationWhatsAppMessageService(
                notificationsWhatsAppMessageEnabled: config('wapi.wapi_notifications_enabled'),
                whatsAppNotificationEventsDispatcherService: $wapNotificationEventsDispatcherService,
                leadNotificationWhatsAppMessageRepository: resolve(LeadNotificationWhatsAppMessageRepository::class),
            );
            return $service;
        });

        $this->app->singleton(TaskNotificationEmailService::class, function () {
            $service = new TaskNotificationEmailService(
                resolve(TaskNotificationEmailRepository::class),
                resolve(ViewsTaskService::class),
                resolve(ClientService::class),
                resolve(ClientyMailerAPIHelper::class),
                config('emails.notifications_email_enabled'),
                config('emails.leads_notification_from_email'),
                config('emails.leads_notification_bcc_email')
            );
            return $service;
        });

        $this->app->singleton(TaskNotificationWhatsAppMessageService::class, function () {
            $wapNotificationEventsDispatcherService = resolve(WhatsAppNotificationEventsDispatcherService::class);

            $service = new TaskNotificationWhatsAppMessageService(
                clientService: resolve(ClientService::class),
                viewsTaskService: resolve(ViewsTaskService::class),
                notificationsWhatsAppMessageEnabled: config('wapi.wapi_notifications_enabled'),
                whatsAppNotificationEventsDispatcherService: $wapNotificationEventsDispatcherService,
                taskNotificationWhatsAppMessageRepository: resolve(TaskNotificationWhatsAppMessageRepository::class),
            );
            return $service;
        });

        $this->app->singleton(SpreadSheetLeadImportHelper::class, function () {
            return new SpreadSheetLeadImportHelper();
        });

        $this->app->singleton(LeadsBulkUploadService::class, function () {
            return new LeadsBulkUploadService(
                resolve(SpreadSheetLeadImportHelper::class),
                resolve(UserService::class),
                resolve(LeadService::class),
                resolve(TagService::class),
                resolve(StatusService::class),
                resolve(AcquisitionChannelService::class),
                resolve(LeadCustomFieldService::class)
            );
        });

        $this->app->singleton(UserNotificationService::class, function () {
            return new UserNotificationService(
                mondayAPIHelper: resolve(MondayAPIHelper2::class),
                userNotificationRepository: new UserNotificationRepository(),
                clientyMailerAPIHelper: resolve(ClientyMailerAPIHelper::class),
                notificationFromEmail: config('emails.leads_notification_from_email')
            );
        });

        $this->app->singleton(HealthService::class, function () {
            return new HealthService(config('app.is_worker'));
        });

        $this->app->singleton(WhatsAppSendingMessageService::class, function () {
            return new WhatsAppSendingMessageService(
                resolve(WhatsAppSendingMessageRepository::class),
                resolve(TimelineEventsDispatcherService::class),
                resolve(WhatsAppEventsDispatcherService::class),
                resolve(PhonesHelper::class),
            );
        });

        $this->app->singleton(WhatsAppSendingService::class, function () {
            return new WhatsAppSendingService(
                resolve(WhatsAppSendingRepository::class),
                resolve(WhatsAppSendingMessageService::class),
                resolve(WhatsAppSendingMessageTextService::class),
                config('whatsapp_sender.min_app_version')
            );
        });

        $this->app->singleton(AutomationFlowChartService::class, function () {
            return new AutomationFlowChartService(
                mermaidChartHelper: resolve(MermaidChartHelper::class),
                automationTaskService: resolve(AutomationTaskService::class),
                automationNewLeadService: resolve(AutomationNewLeadService::class),
                automationProposalService: resolve(AutomationProposalService::class),
                automationEmailSendService: resolve(AutomationEmailSendService::class),
                wAutomationSequenceService: resolve(WAutomationSequenceService::class),
                wAutomationProposalService: resolve(WAutomationProposalService::class),
            );
        });


        $this->app->singleton(WapBotService::class, function () {
            return new WapBotService(
                wapBotRepository: resolve(WapBotRepository::class),
            );
        });

        $this->app->singleton(WapSalesAgentBotService::class, function () {
            return new WapSalesAgentBotService(
                wapSalesAgentBotRepository: resolve(WapSalesAgentBotRepository::class),
            );
        });

        $this->app->singleton(WapBotConversationService::class, function () {
            return new WapBotConversationService(
                conversationRepository: resolve(WapBotConversationRepository::class),
            );
        });

    }


    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Tag::observe(TagObserver::class);
        Lead::observe(LeadObserver::class);
        Note::observe(NoteObserver::class);
        LeadContact::observe(LeadContactObserver::class);
        LeadAttachment::observe(LeadAttachmentObserver::class);
        StatusCategory::observe(StatusCategoryObserver::class);
        TemplateCategory::observe(TemplateCategoryObserver::class);
        LeadContactEmail::observe(LeadContactEmailObserver::class);
        LeadContactPhone::observe(LeadContactPhoneObserver::class);
        WhatsAppAttachment::observe(WhatsAppAttachmentObserver::class);
        GoogleAPIUserToken::observe(GoogleAPIUserTokenObserver::class);
        AutomationEmailSend::observe(AutomationEmailSendObserver::class);
        WAutomationSequence::observe(WAutomationSequenceObserver::class);
        GoogleAPIUserContact::observe(GoogleAPIUserContactObserver::class);
        WAutomationAfterSend::observe(WAutomationAfterSendObserver::class);
        AutomationEmailSendStep::observe(AutomationEmailSendStepObserver::class);
        WAutomationSequenceStep::observe(WAutomationSequenceStepObserver::class);
        AutomationProposalResendRule::observe(AutomationProposalResendRuleObserver::class);
        WAutomationProposalResendRule::observe(WAutomationProposalResendRuleObserver::class);
        AutomationProposalInteractionRule::observe(AutomationProposalInteractionRuleObserver::class);
        AutomationProposalModifyLeadAfterSendRule::observe(AutomationProposalModifyLeadAfterSendRuleObserver::class);
        WAutomationProposalModifyLeadAfterSendRule::observe(WAutomationProposalModifyLeadAfterSendRuleObserver::class);
    }

}
