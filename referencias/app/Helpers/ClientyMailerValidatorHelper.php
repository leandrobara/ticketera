<?php

namespace App\Helpers;

use App\DTO\MailerRequestDTO;
use App\DTO\MailerSendRequestParametersDTO;
use App\DTO\MailerScheduleRequestParametersDTO;
use App\DTO\MailerMassiveScheduleRequestParametersDTO;
use App\Exceptions\Helpers\ClientyMailer\ClientyMailerValidatorException;


class ClientyMailerValidatorHelper
{

    public function validateSendRequestParametersDTO(MailerRequestDTO $mailerSendParamsDTO)
    {
        $fields = ['to' , 'from', 'body', 'subject', 'fromName'];
        foreach ($fields as $field) {
            if (!property_exists($mailerSendParamsDTO, $field)) {
                throw new ClientyMailerValidatorException("The_parameter_{$field}_is_needed");
            }
            if (!$mailerSendParamsDTO->$field) {
                throw new ClientyMailerValidatorException("The_parameter_{$field}_is_needed");
            }
            if ($field == 'from') {
                if (!filter_var($mailerSendParamsDTO->$field, FILTER_VALIDATE_EMAIL)) {
                    throw new ClientyMailerValidatorException("The_email_address_in_parameter_{$field}_is_invalid");
                }
            }
            if ($field == 'to') {
                $to = $mailerSendParamsDTO->$field;
                $toArr = is_array($to) ? $to : [$to];
                foreach ($toArr as $emailAddrStr) {
                    if (!filter_var($emailAddrStr, FILTER_VALIDATE_EMAIL)) {
                        throw new ClientyMailerValidatorException("The_email_address_in_parameter_to_is_invalid");
                    }
                }
            }
        }
    }


    public function validateScheduleRequestParametersDTO(MailerRequestDTO $mailerScheduleParamsDTO)
    {
        $fields = ['to', 'from', 'body', 'subject', 'fromName', 'sendDate'];
        foreach ($fields as $field) {
            if (!property_exists($mailerScheduleParamsDTO, $field)) {
                throw new ClientyMailerValidatorException("The_parameter_{$field}_is_needed");
            }
            if (!$mailerScheduleParamsDTO->$field) {
                throw new ClientyMailerValidatorException("The_parameter_{$field}_is_needed");
            }
            if ($field == 'from') {
                if (!filter_var($mailerScheduleParamsDTO->$field, FILTER_VALIDATE_EMAIL)) {
                    throw new ClientyMailerValidatorException("The_email_address_in_parameter_{$field}_is_invalid");
                }
            }
            if ($field == 'to') {
                $to = $mailerScheduleParamsDTO->$field;
                $toArr = is_array($to) ? $to : [$to];
                foreach ($toArr as $emailAddrStr) {
                    if (!filter_var($emailAddrStr, FILTER_VALIDATE_EMAIL)) {
                        throw new ClientyMailerValidatorException("The_email_address_in_parameter_to_is_invalid");
                    }
                }
            }
        }
    }


    public function validateMassiveScheduleRequestParametersDTO(MailerMassiveScheduleRequestParametersDTO $paramsDTO)
    {
        $fields = ['from', 'body', 'subject', 'fromName', 'sendDate', 'massiveData'];
        foreach ($fields as $field) {
            if (!property_exists($paramsDTO, $field)) {
                throw new ClientyMailerValidatorException("The_parameter_{$field}_is_needed");
            }
            if (!$paramsDTO->$field) {
                throw new ClientyMailerValidatorException("The_parameter_{$field}_is_needed");
            }
            if ($field == 'from') {
                if (!filter_var($paramsDTO->$field, FILTER_VALIDATE_EMAIL)) {
                    throw new ClientyMailerValidatorException("The_email_address_in_parameter_{$field}_is_invalid");
                }
            }
        }
    }

}