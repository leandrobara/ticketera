<?php

namespace App\DTO\WAPI;

use DateTime;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;


class WAPIChatMessageDTO
{

    public string $id;
    public string $type;
    public string $body;
    public bool $hasMedia;
    public int $sentDateTs;
    public string $numberTo;
    public string $mediaData;
    public bool $isForwarded;
    public string $numberFrom;
    public DateTime $sentDate;
    public bool $isFromContact;
    public string | null $mimeType;
    public bool $isFromClientyUser;


    // Esta estructura viene desde WAPI en formato array (ejemplos de json completos al final de esta clase).
    public function __construct(array $WAPIChatMessage)
    {
        $this->type = $WAPIChatMessage['type'];
        $this->body = $WAPIChatMessage['body'];
        $this->hasMedia = $WAPIChatMessage['hasMedia'];
        $this->id = $WAPIChatMessage['id']['_serialized'];
        $this->isFromContact = !$WAPIChatMessage['id']['fromMe'];
        $this->isFromClientyUser = $WAPIChatMessage['id']['fromMe'];
        $this->mimeType = $WAPIChatMessage['_data']['mimetype'] ?? null;
        $this->mediaData = $WAPIChatMessage['hasMedia'] ? ($WAPIChatMessage['_data']['body'] ?? '') : '';

        $this->sentDateTs = $WAPIChatMessage['timestamp'];
        $this->sentDate = new DateTime("@{$WAPIChatMessage['timestamp']}");

        $this->numberTo = Str::before($WAPIChatMessage['to'], '@');
        $this->numberFrom = Str::before($WAPIChatMessage['from'], '@');

        $this->isForwarded = $WAPIChatMessage['isForwarded'] ?? false;
    }


    public function toArray()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'body' => $this->body,
            'mimeType' => $this->mimeType,
            'numberTo' => $this->numberTo,
            'hasMedia' => $this->hasMedia,
            'sentDate' => $this->sentDate,
            'mediaData' => $this->mediaData,
            'numberFrom' => $this->numberFrom,
            'sentDateTs' => $this->sentDateTs,
            'isForwarded' => $this->isForwarded,
            'isFromContact' => $this->isFromContact,
            'isFromClientyUser' => $this->isFromClientyUser,
        ];
    }

}


/*
Ejemplos de distintos types: "chat", "image", "video", "document", "sticker", "ptt" (ptt es audio)

{
  "_data": {
    "id": {
      "fromMe": false,
      "remote": "5491134056538@c.us",
      "id": "B840386FBCFCF031219A7FF5879BFA63",
      "_serialized": "false_5491134056538@c.us_B840386FBCFCF031219A7FF5879BFA63"
    },
    "rowId": 1000016104,
    "body": "Halaaa",
    "type": "chat",
    "t": 1693230922,
    "from": {
      "server": "c.us",
      "user": "5491134056538",
      "_serialized": "5491134056538@c.us"
    },
    "to": {
      "server": "c.us",
      "user": "5491159711575",
      "_serialized": "5491159711575@c.us"
    },
    "self": "in",
    "ack": 3,
    "invis": true,
    "star": false,
    "kicNotified": false,
    "isFromTemplate": false,
    "pollOptions": [],
    "pollInvalidated": false,
    "latestEditMsgKey": null,
    "latestEditSenderTimestampMs": null,
    "mentionedJidList": [],
    "groupMentions": [],
    "isVcardOverMmsDocument": false,
    "isForwarded": false,
    "hasReaction": false,
    "productHeaderImageRejected": false,
    "lastPlaybackProgress": 0,
    "isDynamicReplyButtonsMsg": false,
    "isMdHistoryMsg": false,
    "stickerSentTs": 0,
    "isAvatar": false,
    "lastUpdateFromServerTs": 0,
    "requiresDirectConnection": false,
    "invokedBotWid": null,
    "links": []
  },
  "id": {
    "fromMe": false,
    "remote": "5491134056538@c.us",
    "id": "B840386FBCFCF031219A7FF5879BFA63",
    "_serialized": "false_5491134056538@c.us_B840386FBCFCF031219A7FF5879BFA63"
  },
  "ack": 3,
  "hasMedia": false,
  "body": "Halaaa",
  "type": "chat",
  "timestamp": 1693230922,
  "from": "5491134056538@c.us",
  "to": "5491159711575@c.us",
  "deviceType": "android",
  "isForwarded": false,
  "forwardingScore": 0,
  "isStatus": false,
  "isStarred": false,
  "fromMe": false,
  "hasQuotedMsg": false,
  "hasReaction": false,
  "vCards": [],
  "mentionedIds": [],
  "isGif": false,
  "links": []
}



{
  "_data": {
    "id": {
      "fromMe": false,
      "remote": "5491134056538@c.us",
      "id": "93469F8EF72C2A872902BCC132C00FAE",
      "_serialized": "false_5491134056538@c.us_93469F8EF72C2A872902BCC132C00FAE"
    },
    "rowId": 1000015108,
    "body": "/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEABsbGxscGx4hIR4qLSgtKj04MzM4PV1CR0JHQl2NWGdYWGdYjX2Xe3N7l33gsJycsOD/2c7Z//////////////8BGxsbGxwbHiEhHiotKC0qPTgzMzg9XUJHQkdCXY1YZ1hYZ1iNfZd7c3uXfeCwnJyw4P/Zztn////////////////CABEIAD4AIwMBIgACEQEDEQH/xAAvAAADAQEBAAAAAAAAAAAAAAAAAwQFAgEBAQEBAQEAAAAAAAAAAAAAAAMAAQIE/9oADAMBAAIQAxAAAACYD0ice9UFQXaTsU8u6dxI0cVdBo5Ap4+TX2nFjD3PcsGyarCsc1jc/8QAIxAAAgIBBAIDAQEAAAAAAAAAAQIAEQMEEiExE0EiI1JRcf/aAAgBAQABPwBq3VUdr2iuhUyLSp/kqqnjduQIWJJJ7MysoBYDgCNkBRWJ4AnaqR0REyOigAAwDqZUBDL6mbgjHFQKiLfqU/qFSeIF+NEzVYj5C1cATDuYq5/M+P7qNpLXhoTRKjmZWJxsp9LMWH6UIMKCPqLwE1RmFlOSjdzDzqMyHqfWmIqDDRMJZlraaMJ25A20wE49S7bTRjuwCMF4MOTLZ7jGxKVv7NiGeNQJsWf/xAAfEQACAgIBBQAAAAAAAAAAAAAAAQIhEVEDEBITQWH/2gAIAQIBAT8A0aO+S0PHvRxuxq2SlJ5si3F0eX51yf/EAB8RAAIBAgcAAAAAAAAAAAAAAAABAhFREhMgITFBYf/aAAgBAwEBPwAuYUKvVyfAnsiMYoklIy/dH//Z",
    "type": "image",
    "t": 1693058257,
    "from": {
      "server": "c.us",
      "user": "5491134056538",
      "_serialized": "5491134056538@c.us"
    },
    "to": {
      "server": "c.us",
      "user": "5491159711575",
      "_serialized": "5491159711575@c.us"
    },
    "self": "in",
    "ack": 3,
    "invis": true,
    "star": false,
    "kicNotified": false,
    "interactiveAnnotations": [],
    "deprecatedMms3Url": "https://mmg.whatsapp.net/v/t62.7118-24/21315891_157606400704736_7546272532141645125_n.enc?ccb=11-4&oh=01_AdQ-m5ynGWUXeliSiryr4Ha7g7f10t6c0Evzx1a7OBhv6w&oe=651190EA&mms3=true",
    "directPath": "/v/t62.7118-24/21315891_157606400704736_7546272532141645125_n.enc?ccb=11-4&oh=01_AdQ-m5ynGWUXeliSiryr4Ha7g7f10t6c0Evzx1a7OBhv6w&oe=651190EA",
    "mimetype": "image/jpeg",
    "filehash": "Rc3T5Dqu6pF2PJM8a5rFSTWNljr2qy0j+uhsIpJzcuc=",
    "encFilehash": "V38VG/oA8ZxpNHQA+3aQIEXzHvzcP93wJXp1sKz6fK4=",
    "size": 159051,
    "mediaKey": "Mk447id06BZnIzugysF3rbM8KRh4DL+wzcT+YT5U9go=",
    "mediaKeyTimestamp": 1693058253,
    "isViewOnce": false,
    "width": 900,
    "height": 1600,
    "staticUrl": "",
    "scanLengths": [
      17411,
      64848,
      28570,
      48222
    ],
    "scansSidecar": [],
    "isFromTemplate": false,
    "pollOptions": [],
    "pollInvalidated": false,
    "latestEditMsgKey": null,
    "latestEditSenderTimestampMs": null,
    "mentionedJidList": [],
    "groupMentions": [],
    "isVcardOverMmsDocument": false,
    "isForwarded": false,
    "hasReaction": false,
    "productHeaderImageRejected": false,
    "lastPlaybackProgress": 0,
    "isDynamicReplyButtonsMsg": false,
    "isMdHistoryMsg": false,
    "stickerSentTs": 0,
    "isAvatar": false,
    "lastUpdateFromServerTs": 0,
    "requiresDirectConnection": false,
    "invokedBotWid": null,
    "links": []
  },
  "mediaKey": "Mk447id06BZnIzugysF3rbM8KRh4DL+wzcT+YT5U9go=",
  "id": {
    "fromMe": false,
    "remote": "5491134056538@c.us",
    "id": "93469F8EF72C2A872902BCC132C00FAE",
    "_serialized": "false_5491134056538@c.us_93469F8EF72C2A872902BCC132C00FAE"
  },
  "ack": 3,
  "hasMedia": true,
  "body": "",
  "type": "image",
  "timestamp": 1693058257,
  "from": "5491134056538@c.us",
  "to": "5491159711575@c.us",
  "deviceType": "android",
  "isForwarded": false,
  "forwardingScore": 0,
  "isStatus": false,
  "isStarred": false,
  "fromMe": false,
  "hasQuotedMsg": false,
  "hasReaction": false,
  "vCards": [],
  "mentionedIds": [],
  "isGif": false,
  "links": []
}



{
  "_data": {
    "id": {
      "fromMe": false,
      "remote": "5491134056538@c.us",
      "id": "F7410C955EC07607D67A709A35E9ED42",
      "_serialized": "false_5491134056538@c.us_F7410C955EC07607D67A709A35E9ED42"
    },
    "rowId": 1000014572,
    "body": "/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEABsbGxscGx4hIR4qLSgtKj04MzM4PV1CR0JHQl2NWGdYWGdYjX2Xe3N7l33gsJycsOD/2c7Z//////////////8BGxsbGxwbHiEhHiotKC0qPTgzMzg9XUJHQkdCXY1YZ1hYZ1iNfZd7c3uXfeCwnJyw4P/Zztn////////////////CABEIAEgAKAMBIgACEQEDEQH/xAAuAAACAwEBAAAAAAAAAAAAAAAAAwEEBQIGAQEBAQAAAAAAAAAAAAAAAAAAAQL/2gAMAwEAAhADEAAAANqpZxbNXH2PPyrOyNSk7KNBdJpZAl5S6NZUu2kWBnVjuV6yLaquSQbIQLAgCv/EACUQAAICAQMEAQUAAAAAAAAAAAECAAMRBCExEBIiQVETMjOBkf/aAAgBAQABPwA4TOHf/9k=",
    "type": "video",
    "t": 1692978755,
    "from": {
      "server": "c.us",
      "user": "5491134056538",
      "_serialized": "5491134056538@c.us"
    },
    "to": {
      "server": "c.us",
      "user": "5491159711575",
      "_serialized": "5491159711575@c.us"
    },
    "self": "in",
    "ack": 3,
    "invis": true,
    "star": false,
    "kicNotified": false,
    "interactiveAnnotations": [],
    "deprecatedMms3Url": "https://mmg.whatsapp.net/v/t62.7161-24/30655099_661978675871218_3672954497432898087_n.enc?ccb=11-4&oh=01_AdTy7bwqXEUBLUGo6Zrx2WeuXKopJu4maQ45oORGKrJfnw&oe=651054A3&mms3=true",
    "directPath": "/v/t62.7161-24/30655099_661978675871218_3672954497432898087_n.enc?ccb=11-4&oh=01_AdTy7bwqXEUBLUGo6Zrx2WeuXKopJu4maQ45oORGKrJfnw&oe=651054A3",
    "mimetype": "video/mp4",
    "duration": "3",
    "filehash": "n1aLs7oshhg8QLE0Q7Z/S9KzZkXREiNwaoFGAPtRluY=",
    "encFilehash": "XngA4rFaEMLshVqAQY2GM/0l029gt6CqMXnK1hvtzis=",
    "size": 760606,
    "streamingSidecar": [],
    "mediaKey": "Khecjn+SMRIhAn/9Bn7ug/+CjtExIYtLOAhbahQvxc8=",
    "mediaKeyTimestamp": 1692978753,
    "isViewOnce": false,
    "width": 480,
    "height": 864,
    "staticUrl": "",
    "isFromTemplate": false,
    "pollOptions": [],
    "pollInvalidated": false,
    "latestEditMsgKey": null,
    "latestEditSenderTimestampMs": null,
    "mentionedJidList": [],
    "groupMentions": [],
    "isVcardOverMmsDocument": false,
    "isForwarded": false,
    "hasReaction": false,
    "productHeaderImageRejected": false,
    "lastPlaybackProgress": 0,
    "isDynamicReplyButtonsMsg": false,
    "isMdHistoryMsg": false,
    "stickerSentTs": 0,
    "isAvatar": false,
    "lastUpdateFromServerTs": 0,
    "requiresDirectConnection": false,
    "invokedBotWid": null,
    "links": []
  },
  "mediaKey": "Khecjn+SMRIhAn/9Bn7ug/+CjtExIYtLOAhbahQvxc8=",
  "id": {
    "fromMe": false,
    "remote": "5491134056538@c.us",
    "id": "F7410C955EC07607D67A709A35E9ED42",
    "_serialized": "false_5491134056538@c.us_F7410C955EC07607D67A709A35E9ED42"
  },
  "ack": 3,
  "hasMedia": true,
  "body": "",
  "type": "video",
  "timestamp": 1692978755,
  "from": "5491134056538@c.us",
  "to": "5491159711575@c.us",
  "deviceType": "android",
  "isForwarded": false,
  "forwardingScore": 0,
  "isStatus": false,
  "isStarred": false,
  "fromMe": false,
  "hasQuotedMsg": false,
  "hasReaction": false,
  "duration": "3",
  "vCards": [],
  "mentionedIds": [],
  "isGif": false,
  "links": []
}



{
  "_data": {
    "id": {
      "fromMe": false,
      "remote": "5491134056538@c.us",
      "id": "D3410CC9FBCD0470B0A4BB2DAC66A71C",
      "_serialized": "false_5491134056538@c.us_D3410CC9FBCD0470B0A4BB2DAC66A71C"
    },
    "rowId": 1000014358,
    "body": "/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEABERERESERMVFRMaHBkcGiYjICAjJjoqLSotKjpYN0A3N0A3WE5fTUhNX06MbmJiboyiiIGIosWwsMX46/j///8BERERERIRExUVExocGRwaJiMgICMmOiotKi0qOlg3QDc3QDdYTl9NSE1fToxuYmJujKKIgYiixbCwxfjr+P/////CABEIAGAARAMBIgACEQEDEQH/xAAvAAACAwEBAQAAAAAAAAAAAAAAAwIEBQEGBwEBAQEAAAAAAAAAAAAAAAAAAAID/9oADAMBAAIQAxAAAAD3ObpVBbE29KsAZyAAAV0XUiycxxzoAAAV6OnWKzZvG9prNAoBfIBUnLo3selJrGlFekCzoQp2lCXRkZ2Zo5RuauVoDToFW+FBlsPFQ9wHhE/QQzTSD//EADkQAAICAQIEAQYLCQAAAAAAAAECAxEABBIFEyExMiAiUmFykRQVI0FRU3GBkqGxJDBCQ2KCk8Lh/9oACAEBAAE/ACQoJJ6DDxaDcVCSE+qsTiUDrupwLAF5pdbFqmIjV+gsk+UwVlIYWPoxodIQ37N3sGk69cOh0Td4XOaeOKJWWNCouzflag0gPrOaedZNPzFphu+Y3kOrSWd4gFDKLPXrk2oWGWCM7bkY1flFhh1MCi2kVRZ7kDtnwvS/XR/iGJNDJexlau9EHB5M0ipVjucRdKE6aZavsTeXpTd6ePr37ZFJEhoQhASB0xe3ksB1JAIGGbTNQtfcRivpgejD77xGje9gU16sfVpD0ZelkXYz4ygon/YYvEImqhd3XUYeIRB9leddVuHfI33rdEdSKOaknmADm+H+HEkdh1Z16/OmcxfX7jgdWNC/cRjy8ssLXq7d1LZHNGUBKgn1LWT6pY9tFFB9Jbw61Q8dtH1o+Hvf0dch8Le22SwvJIrBgKH0DFSdWsybhXY58t/Ri8y/O216siVTzLAPyhzYnojNieiM2J6IyHwt7bfrkj7D4ScBcdeZMeoNEDKk+tn9y5G7KKPMck9yBmuGrtDA7KvMYNR79Rj6rWMNRt1RG3YRfs4JJdkY5hsgEef3vtgeeh8kPxZp7KGxR3tk0HNYE/N6s+C117n7MXSMb3Afrg0lGwfyzXpqWMQjiLhZWJ6XRsdcbQazTyBoYpdwcOvS1vIxIiwoeYDsHehX5HIozdidyR3HcZD4W9tv1yXsPF/bh5oJrmEfbgMven9//MS7G7mXf3ZxHjfEdHxGXTwwqYt488oTh4/xGwLh7N/JfDxfWO9tJF/gfJuO8RRFKxQuT3AifOETy6jQQzSrtd7JH7r/xAAaEQACAgMAAAAAAAAAAAAAAAABAgARAxJA/9oACAECAQE/AITi1NK19v8A/8QAGREAAQUAAAAAAAAAAAAAAAAAEQABIjFA/9oACAEDAQE/AFAU52//2Q==",
    "type": "document",
    "t": 1692932471,
    "from": {
      "server": "c.us",
      "user": "5491134056538",
      "_serialized": "5491134056538@c.us"
    },
    "to": {
      "server": "c.us",
      "user": "5491159711575",
      "_serialized": "5491159711575@c.us"
    },
    "self": "in",
    "ack": 3,
    "invis": true,
    "star": false,
    "kicNotified": false,
    "caption": "Facu.pdf",
    "deprecatedMms3Url": "https://mmg.whatsapp.net/v/t62.7119-24/35786487_319281693802526_9170625441485610318_n.enc?ccb=11-4&oh=01_AdRvZ8DaYVDPPAL9zwYpYhlPaoXpb3zrbh2HcaP4XCsJ0g&oe=650F87AD&mms3=true",
    "directPath": "/v/t62.7119-24/35786487_319281693802526_9170625441485610318_n.enc?ccb=11-4&oh=01_AdRvZ8DaYVDPPAL9zwYpYhlPaoXpb3zrbh2HcaP4XCsJ0g&oe=650F87AD&_nc_hot=1692932470",
    "mimetype": "application/pdf",
    "filehash": "xt4GPA6CdBItvS1yA73/oUvicdw9BPL0bEm1sn5uASI=",
    "encFilehash": "BthZo5wGxv5cnX7brBdjNVJw0u1Wj4z8Ht780LlnXtI=",
    "size": 172716,
    "filename": "Facu.pdf",
    "mediaKey": "1HkMYbTU9d1fTs1qYHz69I8g/3Ns1LBW/nMPKkhXTYY=",
    "mediaKeyTimestamp": 1692932468,
    "pageCount": 1,
    "thumbnailDirectPath": "/v/t62.36145-24/25177328_216785027717414_2210704522331227199_n.enc?ccb=11-4&oh=01_AdTCRcno5k2Sb9O6Qejq_qP-HwBWXeC28emzZ3vm5QzvxQ&oe=650F8065",
    "thumbnailSha256": "bIGyL5bvBQxijMIOMR69sHueFdhDY57j0q6QgTrwtuI=",
    "thumbnailEncSha256": "ha18nnOJ24aZlaleBT8OAVfc7JSKDgM+jhnMuo23QuU=",
    "thumbnailHeight": 480,
    "thumbnailWidth": 339,
    "isFromTemplate": false,
    "pollOptions": [],
    "pollInvalidated": false,
    "latestEditMsgKey": null,
    "latestEditSenderTimestampMs": null,
    "mentionedJidList": [],
    "groupMentions": [],
    "isVcardOverMmsDocument": false,
    "isCaptionByUser": false,
    "isForwarded": true,
    "forwardingScore": 1,
    "hasReaction": false,
    "productHeaderImageRejected": false,
    "lastPlaybackProgress": 0,
    "isDynamicReplyButtonsMsg": false,
    "isMdHistoryMsg": false,
    "stickerSentTs": 0,
    "isAvatar": false,
    "lastUpdateFromServerTs": 0,
    "requiresDirectConnection": false,
    "invokedBotWid": null,
    "links": []
  },
  "mediaKey": "1HkMYbTU9d1fTs1qYHz69I8g/3Ns1LBW/nMPKkhXTYY=",
  "id": {
    "fromMe": false,
    "remote": "5491134056538@c.us",
    "id": "D3410CC9FBCD0470B0A4BB2DAC66A71C",
    "_serialized": "false_5491134056538@c.us_D3410CC9FBCD0470B0A4BB2DAC66A71C"
  },
  "ack": 3,
  "hasMedia": true,
  "body": "Facu.pdf",
  "type": "document",
  "timestamp": 1692932471,
  "from": "5491134056538@c.us",
  "to": "5491159711575@c.us",
  "deviceType": "android",
  "isForwarded": true,
  "forwardingScore": 1,
  "isStatus": false,
  "isStarred": false,
  "fromMe": false,
  "hasQuotedMsg": false,
  "hasReaction": false,
  "vCards": [],
  "mentionedIds": [],
  "isGif": false,
  "links": []
}



{
  "_data": {
    "id": {
      "fromMe": false,
      "remote": "5491134056538@c.us",
      "id": "F7F90918B7C63F081B1B2BF22A9D47EB",
      "_serialized": "false_5491134056538@c.us_F7F90918B7C63F081B1B2BF22A9D47EB"
    },
    "rowId": 1000015203,
    "type": "ptt",
    "t": 1693066136,
    "from": {
      "server": "c.us",
      "user": "5491134056538",
      "_serialized": "5491134056538@c.us"
    },
    "to": {
      "server": "c.us",
      "user": "5491159711575",
      "_serialized": "5491159711575@c.us"
    },
    "self": "in",
    "ack": 4,
    "invis": true,
    "star": false,
    "kicNotified": false,
    "deprecatedMms3Url": "https://mmg.whatsapp.net/v/t62.7117-24/26860348_204074602658216_2621001916839717816_n.enc?ccb=11-4&oh=01_AdRegJo9qPsFxpLkQ3UnIy6ZgAciYRsKriYIte9RZpX5UA&oe=651188AC&mms3=true",
    "directPath": "/v/t62.7117-24/26860348_204074602658216_2621001916839717816_n.enc?ccb=11-4&oh=01_AdRegJo9qPsFxpLkQ3UnIy6ZgAciYRsKriYIte9RZpX5UA&oe=651188AC",
    "mimetype": "audio/ogg; codecs=opus",
    "duration": "27",
    "filehash": "dtJltvC+A3cFE1PCdnWTdbhK4Jt0/GCLarmb3ZWIitE=",
    "encFilehash": "GXhTkAhuE2g+Lz5bBkDSYvZSnfCKP9o0NbM9PXS+M44=",
    "size": 66892,
    "mediaKey": "91oPXa08neQiMAnPWiB/y2LoEQfkoRlCC2aFDEsToOg=",
    "mediaKeyTimestamp": 1693066108,
    "waveform": [
      42,
      74,
      80,
      85,
      73,
      69,
      78,
      53,
      83,
      42,
      76,
      29,
      85,
      73,
      76,
      69,
      85,
      70,
      85,
      92,
      83,
      23,
      62,
      36,
      69,
      81,
      49,
      86,
      75,
      81,
      72,
      88,
      70,
      71,
      68,
      63,
      79,
      43,
      84,
      77,
      86,
      88,
      59,
      44,
      75,
      80,
      81,
      71,
      76,
      87,
      34,
      65,
      84,
      76,
      75,
      90,
      79,
      70,
      42,
      83,
      86,
      58,
      85,
      59
    ],
    "isFromTemplate": false,
    "pollOptions": [],
    "pollInvalidated": false,
    "latestEditMsgKey": null,
    "latestEditSenderTimestampMs": null,
    "mentionedJidList": [],
    "groupMentions": [],
    "isVcardOverMmsDocument": false,
    "isForwarded": false,
    "hasReaction": false,
    "ephemeralStartTimestamp": 1693068596,
    "productHeaderImageRejected": false,
    "lastPlaybackProgress": 0,
    "isDynamicReplyButtonsMsg": false,
    "isMdHistoryMsg": false,
    "stickerSentTs": 0,
    "isAvatar": false,
    "lastUpdateFromServerTs": 0,
    "requiresDirectConnection": false,
    "invokedBotWid": null,
    "links": []
  },
  "mediaKey": "91oPXa08neQiMAnPWiB/y2LoEQfkoRlCC2aFDEsToOg=",
  "id": {
    "fromMe": false,
    "remote": "5491134056538@c.us",
    "id": "F7F90918B7C63F081B1B2BF22A9D47EB",
    "_serialized": "false_5491134056538@c.us_F7F90918B7C63F081B1B2BF22A9D47EB"
  },
  "ack": 4,
  "hasMedia": true,
  "body": "",
  "type": "ptt",
  "timestamp": 1693066136,
  "from": "5491134056538@c.us",
  "to": "5491159711575@c.us",
  "deviceType": "android",
  "isForwarded": false,
  "forwardingScore": 0,
  "isStatus": false,
  "isStarred": false,
  "fromMe": false,
  "hasQuotedMsg": false,
  "hasReaction": false,
  "duration": "27",
  "vCards": [],
  "mentionedIds": [],
  "isGif": false,
  "links": []
}
*/