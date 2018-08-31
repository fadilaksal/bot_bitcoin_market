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
        //untuk pengambilan list exchange tergantung pada api masing-masing, jadi tidak harus sama seperti ini
        $ticker = (array)json_decode(getData("https://api.idex.market/returnTicker"));
        $ticker = array_slice($ticker, 0,25);
        $exchangeList = array_keys($ticker);
        $exchangeList = "List Tickers : \n /" . implode(" \n /", $exchangeList);
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
                'text' => "🎁🎁🎁Terimakasih telah menambahkan idexbot \nketik /list untuk melihat exchange yang ada",
            );

            $response = sendRequest('sendMessage', $data);
        }else{
            $messageText    = $messageData->text;

            //memecah string, jika perintahnya ada @ nya, seperti bot yang ada digrup-grup
            $perintah = explode('@', $messageText);
            //nah ini jika perintahnya ada @ nya, maka jumlah array perintah akan > 1
            if(count($perintah) > 1){
                //jika @ setelah perintah usernamenya sama, maka memanggil method set_perintah
                if ($perintah[1] == USERNAME) {
                    $hasil = setPerintah($perintah[0]);
                } else {
                    $hasil = '';
                }
            } else {
                //jika tidak ada @ nya, maka langsung set_perintah
                $hasil = setPerintah($perintah[0]);
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
            case '/list':
                return getExchangeList();
                break;
            case '/start':
                return "🎁🎁🎁 IDEXBOT 🎁🎁🎁\nketik /list untuk melihat exchange yang ada";
                break;
            default:
                $key = str_replace("/","", $perintah);
                $ticker = (array)getExchangeData();
                if(isset($ticker[$key])){
                    $returnMessage =  "IDEX EXCHANGE";
                    $returnMessage .= "\n ☕ Market : " . $key;
                    $returnMessage .= "\n 🍏 High : " . $ticker[$perintah]->high ;
                    $returnMessage .= "\n 🍒 Low : " . $ticker[$perintah]->low;
                    $returnMessage .= "\n 🍍 Last Price : " . $ticker[$perintah]->last;
                    return $returnMessage;
                } else {
                    return "Perintah tidak ditemukan, silahkan masukkan perintah yang lain 😁 $perintah";
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
    //print_r($request);
    if(!$request){
        echo "request error";
    } elseif( !isset($request->update_id) || !isset($request->message) ) {
        echo "tidak ada update_id";
    }
    else
    {
        //print_r($request);
        
        process_message($request->message);
    }
     //print_r($pesanditerima);
?>