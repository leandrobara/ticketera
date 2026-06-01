<?php

namespace App\Helpers;



class CronTabHelper
{

    public static function workerCronIsRunning(string $controllerMethodName): bool
    {
        $cronPartialName = null;
        switch ($controllerMethodName) {
            case 'applyAutomationsProposalResend':
                $cronPartialName = '/automation/proposal/resend/apply';
                break;
            case 'applyAutomationsEmailSendAfterSale':
                $cronPartialName = '/automation/email-send/after-sale/apply';
                break;
            case 'applyAutomationsEmailSendAfterSentProposal':
                $cronPartialName = '/automation/email-send/after-sent-proposal/apply';
                break;
            case 'applyAutomationsEmailSendAfterTagsStatusChange':
                $cronPartialName = '/automation/email-send/after-tags-status-change/apply';
                break;

            case 'applyWAutomationsProposalResend':
                $cronPartialName = '/wautomation/proposal/resend/apply';
                break;
            case 'applyWAutomationsSequenceAfterSale':
                $cronPartialName = '/wautomation/sequence/after-sale/apply';
                break;
            case 'applyWAutomationsSequenceAfterSentProposal':
                $cronPartialName = '/wautomation/sequence/after-sent-proposal/apply';
                break;
            case 'applyWAutomationsSequenceAfterTagStatusChange':
                $cronPartialName = '/wautomation/sequence/after-tags-status-change/apply';
                break;
            case 'retryWAPSenderWAutomationsFailedSendings':
                $cronPartialName = '/wautomation/wap-sender/failed/retry';
                break;
            case 'retryFailedWAPSenderScheduledMessages':
                $cronPartialName = '/wap-sender/scheduled-message/failed/retry';
                break;
        }
        if (!$cronPartialName) {
            return false;
        }

        $command = "ps aux | grep '$cronPartialName' | grep -v grep | grep -v '/bin/sh'";
        $output = shell_exec($command);

        $processCount = 0;
        if ($output || trim($output) != '') {
            $lines = explode("\n", trim($output));
            $processCount = count($lines);
        }

        // $logText = "- controllerMethodName: {$controllerMethodName} \n";
        // $logText .= "- cronPartialName: {$cronPartialName} \n";
        // $logText .= "- processCount: {$processCount} \n";
        // $logText .= "- output: {$output} \n";
        // $logText .= "----------------------------------------------------------------------------- \n";
        // file_put_contents('/var/www/html/clienty/storage/logs/custom.log', $logText, FILE_APPEND);
        

        // Si da 1, es por que detecta el mismo cron actual.
        return $processCount > 1;
    }

}
