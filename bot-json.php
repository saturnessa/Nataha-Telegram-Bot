<?php

/**
 * Генерация текста сообщения
 * @param integer $maxWords
 * @param array $data
 * @return string
 */
function generateText($maxWords, $data) {
    if (empty($data)) {
        throw new \Exception('Bad data format');
    }
    $out = array_rand($data['chain']); // initial word
    while ($out = weighAndSelect($data['chain'][$out])) {
        $text[] = base64_decode($out);
        if (count($text) > $maxWords) {
            break;
        }
    }
    return implode(" ", $text);
}

/**
 * генерация/обновление цепи
 * @param string $message
 * @param array $data
 * @return boolean|int
 */
function train($message, $data) {
    if (empty($message)) {
        return false;
    }
    $array = explode(" ", $message);

    foreach ($array as $num => $val) {
        $val = base64_encode($val);
        $commit = (isset($data['chain'][$val]) ? $data['chain'][$val] : array()); // if there is already a block for this word, keep it, otherwise create one
        $next = $array[$num + 1]; // the next word after the one currently selected
        if (empty($next)) {
            continue; // if this word is EOL, continue to the next word
        }
        $next = base64_encode($next);
        if (isset($commit[$next])) {
            $commit[$next] ++; // if the word already exists, increase the weight
        } else {
            $commit[$next] = 1; // otherwise save the word with a weight of 1
        }
        $data['chain'][$val] = $commit; // commit to the chain
    }
    return $data;
}

/**
 * 
 * @param type $block
 * @return boolean
 */
function weighAndSelect($block) {
    if (empty($block)) {
        return false;
    }

    foreach ($block as $key => $weight) {
        for ($i = 1; $i <= $weight; $i++) {
            $tmp[] = $key;
        }
    }

    $rand = array_rand($tmp);
    return $tmp[$rand];
}

$writeHumanReadable = false;

if (!defined('IS_DEBUG') || !IS_DEBUG)
{
    $input = file_get_contents("php://input"); // Retrieve information sent by webhook
    $sJ = json_decode($input, true); // decode JSON supplied by webhook to PHP array
}
else
{
    $sJ = $testData;
}

if (!is_array($sJ) || !isset($sJ['message']['chat']['id'])) {
    throw new \Exception('Bad data format');
}

$filterRegEx = [
    "urlFilter" => "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@",
    "punctiationFilter" => "/(?<!\w)[.,!]/",
    "newlineFilter" => "/\r|\n/",
];

$chatID = $sJ['message']['chat']['id']; // copy for easier access
$rawText = $sJ['message']['text'];
if (strlen($rawText) > MAX_MESSAGE_LENGTH) {
    throw new \Exception('Data length is bigger then 300');
}
$regExpData = [
    "/натах(.*)сука|натах(.*)тупая|натах(.*)несешь/i" => "CAADAgADCQADaJpdDDa9pygUaeHvAg",
    "/ахах/i" => "CAADAgADnQADaJpdDK2h3LaVb7oGAg",
    "/php|пых/i" => "CAADAgADEwADmqwRGPffQIaMmNCbAg",    
];

$nataha_name = mb_strtolower($rawText);
header("Content-Type: application/json");
foreach ($regExpData as $regExp => $value) 
{
    if (preg_match($regExp, $nataha_name))
    {
        $reply['method'] = "sendSticker";
        $reply['chat_id'] = $chatID;
        $reply['sticker'] = $value;
        echo json_encode($reply);
        die();
    }
}

if (preg_match("/нат(.*)блог/i", $nataha_name) == true) { // chisto reklama
    $reply['method'] = "sendMessage";
    $reply['chat_id'] = $chatID;
    $reply['text'] = "https://www.natalia-blog.ml/";    
    echo json_encode($reply); 
    die();
} 

$fp = null;
$fileName = "data.json";
$chain = [];

if (
    (strpos($input, 'reply_to_message') > 0 && strpos($nataha_name, 'reply_to_message') === false) ||
    preg_match("/ната(.*)|натах|наталия|наталья|наташа|наташка|касперский|анекдот/i", $nataha_name) == true) {
    $chain = json_decode(file_get_contents($fileName), true);
    if (!$chain)
    {
        $chain = [];
    }
    $text = generateText(100, $chain);
    if (!$text)
        $text = "Мне нечего сказать. Мало данных";
    $reply['method'] = "sendMessage";
    $reply['chat_id'] = $chatID;
    $reply['text'] = $text;
    echo json_encode($reply);
}
else 
{   
    header('Content-Type: text/html; charset=utf-8');    
    $preparedText = strtolower($rawText);
    foreach ($filterRegEx as $pattern) {
        $preparedText = preg_replace($pattern, " ", $preparedText);
    }
    $chain = json_decode(file_get_contents($fileName), true);
    if (!$chain)
    {
        $chain = [];
    }
    file_put_contents($fileName, json_encode(train($preparedText, $chain)));
    if ($writeHumanReadable) {
        file_put_contents($chatID . ".json.txt", print_r($chain, true));
    }
}

