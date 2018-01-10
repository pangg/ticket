<?php

namespace App\Console\Commands;

use App\Model\RandCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class Ticket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ticket:begin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ticket begin';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function ask($question, $default = null)
    {
        return parent::ask(mb_convert_encoding($question, 'utf-8', 'utf-8'), $default);
    }

    public function info($string, $verbosity = null)
    {
        parent::info(mb_convert_encoding($string, 'utf-8', 'utf-8'), $verbosity);
    }

    public function error($string, $verbosity = null)
    {
        parent::error(mb_convert_encoding($string, 'utf-8', 'utf-8'), $verbosity);
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $username = 'zpqsunny';
        $password = 'zpq452617435';
        $cookieArray = [];
        $body = '';
        $loginPost = 'https://kyfw.12306.cn/passport/web/login';
        $initUrl = 'https://kyfw.12306.cn/otn/login/init';
        $this->request($initUrl, true, [], false, $cookieArray, $body);
        Yan:
        $yanUrl = 'https://kyfw.12306.cn/passport/captcha/captcha-image?login_site=E&module=login&amp;rand=sjrand&;' . mt_rand(0, 999);
        $res_yan = '';
        while (true) {

            $res_yan = $this->request($yanUrl, true, [], false, $cookieArray, $body);
            if ($res_yan !== '') {

                break;
            }
        }
        $this->info('获取验证码成功');
        preg_match_all("/Set\-Cookie:\h([^\r\n]*);/", $res_yan, $matches);
        if (count($matches) < 2) {

            goto Yan;
        }

        $imgKey = explode('=', $matches[1][1])[1];

        $date = date('Y-m-d', time());
        $baseDir = "/uploads/img/{$date}/";

        $dir = app()->publicPath() . $baseDir;
        if (!is_dir($dir)) {

            mkdir($dir, 0777, true);
        }
        $has_rand = RandCode::where('key', '=', $imgKey)->first();
        if ($has_rand == null) {

            $this->info('未能在数据库中找到答案,请手动输入');

            $file = "{$dir}{$imgKey}.jpeg";
            $f = fopen($file, 'w+');
            $img = explode("\r\n\r\n", $res_yan, 2);
            fwrite($f, $img[1]);
            fclose($f);
            $randCode = new RandCode();
            $randCode->key = $imgKey;
            $randCode->value = '';
            $randCode->path = $baseDir . "{$imgKey}.jpeg";
            $randCode->save();
            $this->info('请打开页面' . URL::to('/image') . '/' . $randCode->id);
            $lng = $this->ask('请输入图片坐标?');
        } else {

            if ($has_rand->value == '') {

                $lng = $this->ask('请输入图片坐标?');
            } else {
                $lng = $has_rand->value;

            }

        }
        //验证码 验证
        $checkYan = 'https://kyfw.12306.cn/passport/captcha/captcha-check'; //post
        $checkData = [
            'answer' => $lng,
            'login_site' => 'E',
            'rand' => 'sjrand'
        ];
        $this->request($checkYan, false, $checkData, false, $cookieArray, $body);
        $json = json_decode($body, true);
        if ($json['result_code'] != "4") {

            unset($cookieArray['_passport_session']);
            unset($cookieArray['_passport_ct']);
            $this->info($json['result_message']);
            $this->info('准备重新获取验证码');
            goto Yan;
        } else {

            $loginData = [
                'username' => $username,
                'password' => $password,
                'appid' => 'otn'
            ];
            $body = '';
            LoginPost:
            $this->request($loginPost, false, $loginData, false, $cookieArray, $body);

            if ($body == '') {

                goto LoginPost;
            }
            $this->info($body);
            $loginJson = json_decode($body, true);
            if ($loginJson['result_code'] == 0) {

                $cookieArray['uamtk'] = $loginJson['uamtk'];
                $this->info('登录成功');
                $uamtkUrl = 'https://kyfw.12306.cn/passport/web/auth/uamtk';
                $this->request($uamtkUrl, false, ['appid' => 'otn'], false, $cookieArray, $body);
                $this->info($body);
                $uamtkJson = json_decode($body, true);
                if ($uamtkJson['result_code'] == 0) {

                    $tk = $uamtkJson['newapptk'];

                    $uamtkClientUrl = 'https://kyfw.12306.cn/otn/uamauthclient';
                    $this->request($uamtkClientUrl, false, ['tk' => $tk], false, $cookieArray, $body);
                    $this->info('uamtkclient');
                    $this->info($body);
                    $uamtkClienJson = json_decode($body,true);
                    if ($uamtkClienJson['result_code'] == 0) {

                        $body = '';
                        $this->request('https://kyfw.12306.cn/otn/queryOrder/queryMyOrder', false, [
                            'queryType' => '1',
                            'queryStartDate' => '2017-01-01',
                            'queryEndDate' => '2018-01-08',
                            'come_from_flag' => 'my_order',
                            'pageSize' => '8',
                            'pageIndex' => '0',
                            'query_where' => 'H',
                            'sequeue_train_name' => '',
                        ], false, $cookieArray, $body);
                        $this->info('输出授权后的信息');
                        $this->info($body);
                        exit(0);
                    }
                }


            } else {

                $this->error('登录错误:' . $loginJson['result_message']);
            }

        }

    }

    private function request(string $url, bool $get = false, array $data = [], bool $follow = false, array &$cookieArray, string &$body): string
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
            $this->info('cook_str: ' . $cookieStr);
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
            preg_match_all("/Set\-Cookie:\h([^\r\n]*);/", $res, $matches);
            if (count($matches) == 2) {

                foreach ($matches[1] as $match) {

                    $cok = explode('=', $match);
                    $cookieArray[$cok[0]] = $cok[1];
                }
            }
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $body = substr($res, $headerSize);

            curl_close($curl);
            return $res;
        }
    }
}
