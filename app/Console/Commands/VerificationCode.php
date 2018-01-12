<?php

namespace App\Console\Commands;

use App\Model\RandCode;
use Illuminate\Console\Command;

class VerificationCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verification_code:get {date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'rand_code get';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $date = $this->argument('date');
        $body = '';
        $head = '';
        for ($i = 1; $i <= 1000; $i++) {

            Y:
            $imgUrl = 'https://kyfw.12306.cn/passport/captcha/captcha-image?login_site=E&module=login&rand=sjrand&' . mt_rand(0, 9999);
            $this->request($imgUrl, true, [], false, $body, $head);
            if ($body != '' && $head != '') {

                preg_match_all("/Set\-Cookie:\h([^\r\n]*);/", $head, $matches);
                if (count($matches) == 2) {

                    $imgKey = explode('=', $matches[1][1])[1];

                    $baseDir = "/uploads/img/{$date}/";

                    $dir = app()->publicPath() . $baseDir;
                    if (!is_dir($dir)) {

                        mkdir($dir, 0777, true);
                    }
                    $file = "{$dir}{$imgKey}.jpeg";

                    if (!file_exists($file)) {

                        $f = fopen($file, 'w+');
                        $img = $body;
                        fwrite($f, $img);
                        fclose($f);
                        $randCode = new RandCode();
                        $randCode->key = $imgKey;
                        $randCode->value = '';
                        $randCode->path = $baseDir . "{$imgKey}.jpeg";
                        $randCode->save();
                    }
                }
            } else {
                //保证 1k次有效
                goto Y;
            }
        }

    }

    private function request(string $url, bool $get = false, array $data = [], bool $follow = false,
                             string &$body, string &$head): string
    {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        global $cookieArray;
        if (count($cookieArray) > 0) {

            $cookieStr = "";
            foreach ($cookieArray as $key => $value) {

                $cookieStr .= "{$key}={$value};";
            }
            curl_setopt($curl, CURLOPT_COOKIE, $cookieStr);

        }
        if ($get) {

            $getData = '';

            foreach ($data as $key => $value) {

                $getData .= "&{$key}=" . urlencode($value);
            }
            $getData = '?' . substr($getData, 1);
            curl_setopt($curl, CURLOPT_URL, $url . $getData);
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        } else {

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, $follow);

        $res = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {

            printf(curl_error($curl));
            curl_close($curl);
            return '';
        } else {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $head = substr($res, 0, $headerSize);
            $body = substr($res, $headerSize);
            curl_close($curl);
            return $res;
        }
    }
}
