<?php

namespace App\Console\Commands;

use App\Model\RandCode;
use Illuminate\Console\Command;

class CheckCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:code';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'check code';

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
        $cookieArray = [];
        $head = '';
        $body = '';
        while (true) {

            $yanUrl = 'https://kyfw.12306.cn/passport/captcha/captcha-image?login_site=E&module=login&amp;rand=sjrand&;' . mt_rand(0, 999);
            $this->request($yanUrl, true, [], false, $cookieArray, $body, $head);
            $md5 = md5($body);
            $has_rand = RandCode::where('md5', '=', $md5)->first();
            if ($has_rand == null) {

                $date = date('Y-m-d', time());
                $baseDir = "/uploads/img/{$date}/";

                $dir = app()->publicPath() . $baseDir;
                if (!is_dir($dir)) {

                    mkdir($dir, 0777, true);
                }

                $file = "{$dir}{$md5}.jpeg";
                $f = fopen($file, 'w+');
                $img = $body;
                fwrite($f, $img);
                fclose($f);
                $randCode = new RandCode();
                $randCode->md5 = $md5;
                $randCode->value = '';
                $randCode->path = $baseDir . "{$md5}.jpeg";
                $randCode->is_ok = 0;
                $randCode->save();
            } else {

                if ($has_rand->value != '' && $has_rand->is_ok = 0) {

                    $checkYan = 'https://kyfw.12306.cn/passport/captcha/captcha-check'; //post
                    $checkData = [
                        'answer' => $has_rand->value,
                        'login_site' => 'E',
                        'rand' => 'sjrand'
                    ];
                    $this->request($checkYan, false, $checkData, false, $cookieArray, $body, $head);
                    $json = json_decode($body, true);
                    $this->info('验证码返回' . $body);
                    if ($json['result_code'] != "4") {

                        unset($cookieArray['_passport_session']);
                        unset($cookieArray['_passport_ct']);
                        $this->error('picture id: '.$has_rand->id .' check fail');

                    } else {

                        $has_rand->is_ok = 1;
                        $has_rand->save();
                    }

                }
            }
        }

    }

    private function request(string $url, bool $get = false, array $data = [], bool $follow = false,
                             array &$cookieArray = [], string &$body, string &$head): string
    {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);

        if (count($cookieArray) > 0) {

            $cookieStr = "";
            foreach ($cookieArray as $key => $value) {

                $cookieStr .= "{$key}={$value};";
            }
            $this->info("url: {$url}  cook_str: {$cookieStr} ");
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

            preg_match_all("/Set\-Cookie:\h([^\r\n]*);/", $head, $matches);
            if (count($matches) == 2) {

                foreach ($matches[1] as $match) {

                    $cok = explode('=', $match);
                    $cookieArray[$cok[0]] = $cok[1];
                }
            }

            curl_close($curl);
            return $res;
        }
    }

}
