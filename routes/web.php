<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $ip = sprintf("%s:%s", request()->server('SERVER_ADDR'), request()->server('SERVER_PORT'));

    return view('welcome', ['ip' => $ip]);
});

Route::get('/bing', function () {
    return view('bing');
});

Route::post('/bing', function (\Illuminate\Http\Request $request) {
    $fileName = "";
    if ($request->get('text')) {
        $fileName = "/audio/test." . time() . ".mp3";

        $accessTokenUri = "https://api.cognitive.microsoft.com/sts/v1.0/issueToken";
        $apiKey = Config::get('azure.bing.key');

        $options = [
            'http' => [
                'header' => "Ocp-Apim-Subscription-Key: " . $apiKey . "\r\n" .
                    "content-length: 0\r\n",
                'method' => 'POST',
            ],
        ];

        $context = stream_context_create($options);
        $access_token = file_get_contents($accessTokenUri, false, $context);

        if (!$access_token) {
            throw new Exception("Problem with $accessTokenUri");
        } else {
            $ttsServiceUri = "https://speech.platform.bing.com:443/synthesize";

            $doc = new DOMDocument();

            $root = $doc->createElement("speak");
            $root->setAttribute("version", "1.0");
            $root->setAttribute("xml:lang", "en-us");

            $voice = $doc->createElement("voice");
            $voice->setAttribute("xml:lang", "en-GB");
            $voice->setAttribute("xml:gender", "Female");
            $voice->setAttribute("name", "Microsoft Server Speech Text to Speech Voice (en-GB, HazelRUS)");

            $text = $doc->createTextNode($request->get('text'));

            $voice->appendChild($text);
            $root->appendChild($voice);
            $doc->appendChild($root);
            $data = $doc->saveXML();

            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/ssml+xml\r\n" .
                        "X-Microsoft-OutputFormat: audio-16khz-128kbitrate-mono-mp3\r\n" .
                        "Authorization: " . "Bearer " . $access_token . "\r\n" .
                        "X-Search-AppId: 07D3234E49CE426DAF29772419F436CA\r\n" .
                        "X-Search-ClientID: 1ECFAE91408841A480F00935DC390860\r\n" .
                        "User-Agent: TTSPHP\r\n" .
                        "content-length: " . strlen($data) . "\r\n",
                    'method'  => 'POST',
                    'content' => $data,
                ),
            );

            $context = stream_context_create($options);

            $result = file_get_contents($ttsServiceUri, false, $context);
            if (!$result) {
                throw new Exception("Problem with $ttsServiceUri");
            } else {
                $file = fopen(public_path($fileName), "w");
                fwrite($file, $result);
                fclose($file);
            }
        }
    }

    return view('bing', ['file' => $fileName]);
});
