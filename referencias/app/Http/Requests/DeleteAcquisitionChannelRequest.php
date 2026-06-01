<?php

namespace App\Http\Requests;

use App\Models\AcquisitionChannel;

class DeleteAcquisitionChannelRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->channel->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'acquisition_channel_client_does_not_match_with_authenticated_client'
                );
                return false;
            }
            if (request()->channel->leads->count() >= 1) {
                $validator->errors()->add('channel_has_leads', 'acquisition_channel_has_associated_leads');

                return false;
            }
            $count = AcquisitionChannel::where('client_id', request()->input('client')->id)->count();
            if ($count <= 1) {
                $validator->errors()->add('one_acquisition_channel_left', 'acquisition_channels_cannot_be_empty');

                return false;
            }
        });
    }
}
