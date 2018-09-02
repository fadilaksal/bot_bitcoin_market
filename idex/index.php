<?php
    define('TOKEN', '<token>');
    define('USERNAME', '<username>');
    // define('EXCHANGE_URL', 'http://localhost/bitcoin_market_exchange_list');

    //mengambil seluruh data exchange
    function getExchangeData()
    {
        $ticker = getData("https://api.idex.market/returnTicker");
        return json_decode($ticker);
    }

    //mengambil list exchange
    function getExchangeList()
    {
        $ticker = (array)json_decode(getData("https://api.idex.market/returnTicker"));
        // $ticker = array_slice($ticker, 0,25);
        // $exchangeList = array_keys($ticker);
        // $exchangeList = "List Tickers : \n /" . implode(" \n /", $exchangeList);
        $exchangeList = "List Tickers IDEX : ";
        $i = 1;
        foreach($ticker as $key => $value){
            $exchangeList .= "\n $i. /$key ";
            if($i == 100){
                break;
            }
            $i++;
        }
        // print_r($exchangeList);
        return $exchangeList;
    }

    //mengambil data dari api idex
    function getData($url)
    {
        $curl_handle=curl_init();
        curl_setopt($curl_handle,CURLOPT_URL,$url);
        curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
        curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl_handle, CURLOPT_COOKIEJAR, 'cookies.txt');
        curl_setopt($curl_handle, CURLOPT_COOKIEFILE, 'cookies.txt');
        curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:35.0) Gecko/20100101 Firefox/35.0');
        $result = curl_exec($curl_handle);
        curl_close($curl_handle);

        return $result;
    }
    
    function getUrl($method)
    {
        return 'https://api.telegram.org/bot' . TOKEN . "/$method";
    }

    //mengirim request ke api telegram berdasarkan method yang diisi
    function sendRequest($method, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, getUrl($method));
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 
        $response = curl_exec ($ch);
        curl_close ($ch);
 
        return $response;
    }

    // menggunakan long polling
    function getUpdate($offset)
    {
        //mengambil method getupdate dari api telegram(karena menggunakan long polling)
        $url = getUrl('getUpdates') . "?offset=$offset";
        $request = file_get_contents($url);
        $response = json_decode($request);
        if($response->ok){
            return $response->result;
        } else {
            return array();
        }
    }

    //ini juga untuk long polling
    function runBot()
    {
        $updateId = 0;
        //jika file last_update_id ada, maka updateId digantikan oleh isi file tersebut
        if (file_exists('last_update_id')) {
            $updateId = (int)file_get_contents('last_update_id');
        } 

        //mengambil message terakhir berdasarkan update_id ke atas, contoh misal 712, maka yang diambil adalah 712, 713, sampai terakhir
        $updates = getUpdate($updateId);
        

        foreach ($updates as $message) {
            $updateId = $message->update_id;
            $messageData = $message->message;
            if(isset($messageData)){
                process_message($messageData);
            }

            //ubah isi dari file last_update_id dengan updateid yang terakhir
            file_put_contents('last_update_id', $updateId + 1);
            // return $response;
        }
    }

    function process_message($messageData)
    {
        $chatId = $messageData->chat->id;
        $messageId = $messageData->message_id;
        //jika bot baru ditambahkan di grup atau bot dikick dari grup
        if((isset($messageData->new_chat_participant) && $messageData->new_chat_participant->username == USERNAME) || (isset($messageData->left_chat_participant) && $messageData->left_chat_participant->username == USERNAME)){
            $data = array(
                'chat_id' => $chatId,
                'text' => "🎁🎁🎁Terimakasih telah menambahkan idexbot \nketik /help untuk melihat exchange yang ada",
            );

            $response = sendRequest('sendMessage', $data);
        }else{
            $messageText    = $messageData->text;

            //memecah string, jika perintahnya dari grup
            if($messageData->chat->type == 'group' || $messageData->chat->type == 'supergroup'){
            $perintah = explode('@', $messageText);
            //nah ini jika perintahnya ada @ nya, maka jumlah array perintah akan > 1
                if(count($perintah) > 1){
                    //jika @ setelah perintah usernamenya sama, maka memanggil method set_perintah
                    if ($perintah[1] == USERNAME) {
                        $hasil = setPerintah($perintah[0]);
                    } else {
                        echo "test";
                        $hasil = '';
                    }
                } else {
                    echo "test2";
                    $hasil = '';
                }
            } else {
                //jika bukan grup, maka langsung set_perintah
                $hasil = setPerintah($messageText);
            }

            //jika hasilnya tidak kosong, maka akan diproses
            if($hasil != ''){
                //jika hasilnya lebih dari 4096(maksimal karakter untuk chat telegram), maka hasil akan dipotong
                if(strlen($hasil) > 4096){
                    $hasil = substr($hasil, 0, 4096);
                }
                $data = array(
                    'chat_id' => $chatId,
                    'text' => $hasil
                );

                $response = sendRequest('sendMessage', $data);
            }
        }
    }

    //list perintah ada disini
    function setPerintah($perintah)
    {
        switch ($perintah) {
            case '/listidex':
                return getExchangeList();
                break;
            case '/help':
                return "🎁🎁🎁 IDEXBOT 🎁🎁🎁\nketik /listidex untuk melihat exchange yang ada";
                break;
            default:
                $key = str_replace("/","", $perintah);
                $ticker = (array)getExchangeData();
                if(isset($ticker[$key])){
                    $high = $ticker[$key]->high != null ? $ticker[$key]->high*100000000 : 0;
                    $low = $ticker[$key]->low != null ? $ticker[$key]->low*100000000 : 0;
                    $last = $ticker[$key]->last != null ? $ticker[$key]->last*100000000 : 0;
                    $returnMessage =  "IDEX EXCHANGE";
                    $returnMessage .= "\n ☕ Market : " . $key;
                    $returnMessage .= "\n 🍏 High : " . $high;
                    $returnMessage .= "\n 🍒 Low : " . $low;
                    $returnMessage .= "\n 🍍 Last Price : " . $last;
                    return $returnMessage;
                } else {
                    // return "$perintah Perintah tidak ditemukan, silahkan masukkan perintah yang lain 😁";
                }
                break;
        }
    }

    //method untuk long polling
    //while (true) {
        //sleep(2);
        //runBot();
    //}

     //method untuk webhook
    $entityBody = file_get_contents('php://input');
    $request = json_decode($entityBody);
    
    if(!$request){
        echo "request error";
    } elseif( !isset($request->update_id) || !isset($request->message) ) {
        echo "tidak ada update_id";
    } else{
        process_message($request->message);
    }
?>