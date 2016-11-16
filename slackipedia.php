<?php
/**
 * Slackipedia-bot - perform Wikipedia queries with Slack slash commands
 * See here for the tutorial: https://api.slack.com/tutorials/your-first-slash-command
 * Made by Niko Heikkilä, November 2016
 * You may treat this file as if it were in public domain
 */

/* Verify that the request came from our team */
$TOKEN = 'YOUR-SLASH-COMMAND-TOKEN-HERE';

if ($_POST['token'] !== $TOKEN)
{
    header('HTTP/1.0 403 Forbidden');
    exit();
}

/* URL for the incoming webhooks in Slack */
$slack_webhook_url = "https://hooks.slack.com/services/your-webhook-url-here";

/* Define the language for integration */
$wiki_lang = "en";

/* Limit the search results to 3 */
$search_limit = "3";

/* Identify yourself to WikiMedia API */
$user_agent = "Slackipedia/1.0 (https://pelilegacy.slack.com; tekniikka@pelilegacy.fi)";

/* POST variables from the cURL call */
$command    = $_POST['wiki'];
$text       = $_POST['text'];
$channel_id = $_POST['channel_id'];
$user_id    = $_POST['user_id'];
$user_name  = $_POST['user_name'];

/* Encode the text for search string */
$encoded_text = urlencode($text);

/* Create the search URL */
$wiki_url = "https://" . $wiki_lang . ".wikipedia.org/w/api.php?action=opensearch&search=" . $encoded_text . "&format=json&limit=" . $search_limit;

/* Perform the cURL */
$wiki_call = curl_init($wiki_url);
curl_setopt($wiki_call, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($wiki_call, CURLOPT_USERAGENT, $user_agent);
$wiki_response = curl_exec($wiki_call);

if ($wiki_response === FALSE)
{
    $message_text = "Wikipedia ei vastannut ajoissa. Virhe: " . curl_error($wiki_call);
}
else
{
    $message_text = "";
}

curl_close($wiki_call);

/* Decode the response */
if ($wiki_response !== FALSE)
{
    $wiki_array          = json_decode($wiki_response);
    $other_options       = $wiki_array[3];
    $first_item          = array_shift($other_options);
    $other_options_count = count($other_options);

    if (strpos($wiki_array[2][0], "may refer to:") !== FALSE)
    {
        $disambiguation_check = TRUE;
    }

    $message_primary_title   = $wiki_array[1][0];
    $message_primary_summary = $wiki_array[2][0];
    $message_primary_link    = $wiki_array[3][0];

    if (empty($wiki_array[1]))
    {
        $message_text = "Ei löytynyt mitään hakusanalla _" . $text . "_.";
    }
    else
    {
        if ($disambiguation_check)
        {
            $message_text .= "Katso täsmennyssivu ";
            $message_text .= "_<" . $message_primary_link . "|" . $text . ">_?\n\n";
        }
        else
        {
            $message_text .= $message_primary_summary . "\n\n";
        }

        $message_other_title = "Muita kiinnostavia tuloksia.\n";

        foreach ($other_options as $value)
        {
            $message_other_options .= $value . "\n";
        }
    }
}

$emojis = [":crystal_ball:", ":shipit:", ":computer:"];
$rand_emoji = array_rand($emojis);

$data = [
    "channel"   => $channel_id,
    "text"      => "<@" . $user_id . "|" . $user_name . "> haki sanalla " . $text . " " . $emojis[$rand_emoji],
    "mrkdwn"    => TRUE,
    "attachments" => [
        [
            "color"      => "#b0c4de",
            "title"      => $message_primary_title,
            "title_link" => $message_primary_link,
            "fallback"   => $message_text,
            "text"       => $message_text,
            "mrkdwn_in"  => ["fallback", "text"],
            "fields" => [
                [
                    "title" => $message_other_title,
                    "value" => $message_other_options
                ]
            ],
            "footer" => "Powered by WikiMedia API"
        ]
    ]
];

$json_string = json_encode($data);

$slack_call = curl_init($slack_webhook_url);
curl_setopt($slack_call, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($slack_call, CURLOPT_POSTFIELDS, $json_string);
curl_setopt($slack_call, CURLOPT_CRLF, TRUE);
curl_setopt($slack_call, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($slack_call, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Content-Length: " . strlen($json_string)
]);

$result = curl_exec($slack_call);
curl_close($slack_call);
