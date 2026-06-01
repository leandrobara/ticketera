<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class WAutomationLog extends Model
{

    use SoftDeletes;

    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'WAutomationsLogs';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'integer',
            'lead_id' => 'integer',
            'client_id' => 'integer',
            'event_log_ids' => 'array',
            'lead_sale_id' => 'integer',
            'whatsapp_sending_id' => 'integer',
            'wautomation_sequence_id' => 'integer',
            'wautomation_proposal_id' => 'integer',
            'wautomation_after_send_id' => 'integer',
            'whatsapp_sending_message_id' => 'integer',
            'wautomation_sequence_step_id' => 'integer',
            'wautomation_proposal_resend_rule_id' => 'integer',
            'wautomation_proposal_modify_lead_after_send_rule_id' => 'integer',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];
        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    // @todo cambiar nombre a triggeringWhatsAppSending
    public function whatsAppSending()
    {
        return $this->belongsTo(WhatsAppSending::class, 'whatsapp_sending_id');
    }

    // @todo cambiar nombre a triggeringWhatsAppSendingMessage
    public function whatsAppSendingMessage()
    {
        return $this->belongsTo(WhatsAppSendingMessage::class, 'whatsapp_sending_message_id');
    }


    public function sentWhatsAppSendingMessage()
    {
        return $this->hasOne(WhatsAppSendingMessage::class, 'wautomation_log_id');
    }


    public function wAutomationAfterSend()
    {
        return $this->belongsTo(WAutomationAfterSend::class, 'wautomation_after_send_id');
    }


    public function wAutomationProposal()
    {
        return $this->belongsTo(WAutomationProposal::class, 'wautomation_proposal_id');
    }


    public function wAutomationProposalResendRule()
    {
        return $this->belongsTo(WAutomationProposalResendRule::class, 'wautomation_proposal_resend_rule_id');
    }


    public function wAutomationSequence()
    {
        return $this->belongsTo(WAutomationSequence::class, 'wautomation_sequence_id');
    }


    public function wAutomationSequenceStep()
    {
        return $this->belongsTo(WAutomationSequenceStep::class, 'wautomation_sequence_step_id');
    }


    public function wAutomationProposalModifyLeadAfterSendRule()
    {
        return $this->belongsTo(
            WAutomationProposalModifyLeadAfterSendRule::class,
            'wautomation_proposal_modify_lead_after_send_rule_id'
        );
    }

}
