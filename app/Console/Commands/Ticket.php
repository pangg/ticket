<?php

namespace App\Console\Commands;

use App\Model\RandCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
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
//        return parent::ask(mb_convert_encoding($question, 'gb2312', 'utf-8'), $default);
    }

    public function info($string, $verbosity = null)
    {
        parent::info(mb_convert_encoding($string, 'utf-8', 'utf-8'), $verbosity);
//        parent::info(mb_convert_encoding($string, 'gb2312', 'utf-8'), $verbosity);
    }

    public function error($string, $verbosity = null)
    {
        parent::error(mb_convert_encoding($string, 'utf-8', 'utf-8'), $verbosity);
//        parent::error(mb_convert_encoding($string, 'gb2312', 'utf-8'), $verbosity);
    }

    public function show(array $rowName = [], array $data)
    {
        $i = 0;
        foreach ($data as $item) {

            $write = "\n";

            $i++;

            $write .= "- {$i}";

            foreach ($item as $key => $value) {

                $str = " | {$rowName[$key]} : [  \033[32m {$value} \033[0m  ";
                while (mb_strlen($str) < 30) {

                    $str .= " ";
                }
                $write .= $str . " ]";
            }
            $write .= "\n";
            echo $write;
        }
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $username = '';
        $password = '';
        $cookieArray = [];
        $body = '';
//        $train_date = $this->ask('请输入去程日期 格式：' . date('Y-m-d'));
//        $back_train_date = $this->ask('请输入反程日期 格式：' . date('Y-m-d'));
        $train_date = '2018-01-18';
        $back_train_date = '2018-01-18';
        $from_name = '';
        $from_code = '';
        $to_name = '';
        $to_code = '';
        while (true) {

//            $from_name = $this->ask('从哪里出发?');
            $from_name = '北京';
            $v = Config::get('ticket.address.' . $from_name);
            if ($v != null) {

                $from_code = $v;
                break;
            }
            $this->error('address is error');
        }
        while (true) {

//            $to_name = $this->ask('到哪里去?');
            $to_name = '上海';
            $v = Config::get('ticket.address.' . $to_name);
            if ($v != null) {

                $to_code = $v;
                break;
            }
            $this->error('address is error');
        }
        $name = '';
        $idCard = '';
        $phone = '';
        $repeatSubmitToken = '';
        $ticketInfoForPassengerForm = '';
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
                    $uamtkClientJson = json_decode($body, true);
                    if ($uamtkClientJson['result_code'] == 0) {

                        $this->info('进入循环查询');
                        while (true) {
                            //循环查询
                            $queryZ = 'https://kyfw.12306.cn/otn/leftTicket/queryZ';

                            $queryData = [
                                'leftTicketDTO.train_date' => $train_date,
                                'leftTicketDTO.from_station' => $from_code,
                                'leftTicketDTO.to_station' => $to_code,
                                'purpose_codes' => 'ADULT'
                            ];
                            $body = '';
                            $this->request($queryZ, true, $queryData, false, $cookieArray, $body);
                            $this->info('输出查询返回的内容');
                            $this->info($body);
                            $queryJson = json_decode($body, true);
                            if ($queryJson['httpstatus'] == 200) {

                                $head = [
                                    'cc' => '车次',
                                    'cf' => '出发时间',
                                    'dd' => '到达时间',
                                    'ls' => '历时',
                                    'swz' => '商务座/特等座',
                                    'ydz' => '一等座',
                                    'edz' => '二等座',
                                    'gjrw' => '高级软卧',
                                    'rw' => '软卧',
                                    'dw' => '动卧',
                                    'yw' => '硬卧',
                                    'rz' => '软座',
                                    'yz' => '硬座',
                                    'wz' => '无座',
                                    'qt' => '其他',
                                    'ok' => '是否可订票',
                                    'secretStr' => '车次标记',
                                ];
                                $result = [];
                                foreach ($queryJson['data']['result'] as $item) {

                                    $d = explode('|', $item);

                                    $result[] = [
                                        'cc' => $d[3],  //车次
                                        'cf' => $d[8],  //出发时间
                                        'dd' => $d[9],  //到达时间
                                        'ls' => $d[10], //历时
                                        'swz' => $d[32], //商务座/特等座 ok
                                        'ydz' => $d[31], //一等座 ok
                                        'edz' => $d[30], //二等座 ok
                                        'gjrw' => $d[21], //高级软卧 21
                                        'rw' => $d[23], //软卧 ok
                                        'dw' => $d[33], //动卧 ok
                                        'yw' => $d[28], //硬卧 ok
                                        'rz' => $d[3],  //软座
                                        'yz' => $d[29], //硬座 ok
                                        'wz' => $d[26], //无座 ok
                                        'qt' => $d[22], //其他 ok
                                        'ok' => $d[0] == '' ? false : true, //是否可订票 ok
                                        'secretStr' => urldecode($d[0])     //当前车次标记
                                    ];

                                }
                                $this->show($head, $result);
                                foreach ($result as $item) {

                                    if ($item['ok']) {

                                        $submitOrderRequestUrl = 'https://kyfw.12306.cn/otn/leftTicket/submitOrderRequest';

                                        $cookieArray['_jc_save_fromDate'] = $train_date;
                                        $cookieArray['_jc_save_fromStation'] = str_replace("\\", "%", json_encode($from_name)) . '%2C' . $from_code;
                                        $cookieArray['_jc_save_showIns'] = 'true';
                                        $cookieArray['_jc_save_toDate'] = $back_train_date;
                                        $cookieArray['_jc_save_toStation'] = str_replace("\\", "%", json_encode($to_name)) . '%2C' . $to_code;
                                        $cookieArray['_jc_save_wfdc_flag'] = 'dc';          //dc 单程 wc 往返

                                        $submitOrderRequestData = [
                                            'secretStr' => $item['secretStr'],                              //查询票的 [0]字段
                                            'train_date' => $train_date,                    //去程日期
                                            'back_train_date' => $back_train_date,          //反程日期
                                            'tour_flag' => 'dc',                            //dc 单程 wc 往返
                                            'purpose_codes' => 'ADULT',                     //目前未知 固定
                                            'query_from_station_name' => $from_name,
                                            'query_to_station_name' => $to_name,
                                            'undefined' => '',                              //目前未知 固定为空
                                        ];
                                        // 没有返回
                                        $this->request($submitOrderRequestUrl, false, $submitOrderRequestData, false, $cookieArray, $body);
                                        $submitOrderJson = json_decode($body, true);
                                        if ($submitOrderJson['httpstatus'] == 200 && $submitOrderJson['status'] == true) {

                                            globalRepeatSubmitToken:
                                            $this->info('请求订单成功');
                                            $initDc = 'https://kyfw.12306.cn/otn/confirmPassenger/initDc';
                                            $this->request($initDc, false, ['_json_att' => ''], true, $cookieArray, $body);
                                            $this->info('获取参数 REPEAT_SUBMIT_TOKEN:');
                                            preg_match("/globalRepeatSubmitToken\h=\h\'.*\'\;/", $body, $ma);

                                            if ($ma[0] != '') {

                                                $st = str_replace("globalRepeatSubmitToken = '", '', $ma[0]);
                                                $repeatSubmitToken = str_replace("';", '', $st);

                                            } else {

                                                goto globalRepeatSubmitToken;
                                            }
                                            preg_match("/ticketInfoForPassengerForm=.*\}\;/", $body, $ma2);
                                            if ($ma2[0] != '') {

                                                //是个json
                                                $st1 = str_replace("ticketInfoForPassengerForm=", '', $ma2[0]);
                                                $st2 = str_replace(";", '', $st1);
                                                $ticketInfoForPassengerForm = str_replace("'", '"', $st2);
                                                $ticketInfoForPassengerForm = json_decode($ticketInfoForPassengerForm, true);
                                            }

                                            //订单验证
                                            $checkOrderInfoUrl = 'https://kyfw.12306.cn/otn/confirmPassenger/checkOrderInfo';

                                            $checkOrderInfoData = [
                                                'cancel_flag' => '2',
                                                'bed_level_order_num' => '000000000000000000000000000000',
                                                'passengerTicketStr' => "O,0,1,{$name},1,{$idCard},{$phone},N",
                                                'oldPassengerStr' => "{$name},1,{$idCard},1_",
                                                'tour_flag' => 'dc',                                //dc 单程 wc 往返
                                                'randCode' => '',                                   //默认空
                                                'whatsSelect' => '1',                               //暂时默认为1
                                                '_json_att' => '',                                  //默认空
                                                'REPEAT_SUBMIT_TOKEN' => $repeatSubmitToken,        //
                                            ];
                                            $this->info('开始请求验证订单');
                                            $this->request($checkOrderInfoUrl, false, $checkOrderInfoData, false, $cookieArray, $body);
                                            $this->info('看看什么返回 checkOrderInfo：' . $body);
                                            $checkOrderInfoDataJson = json_decode($body,true);
                                            if ($checkOrderInfoDataJson['httpstatus'] == 200 && $checkOrderInfoDataJson['status'] == true) {

                                                $confirm = 'https://kyfw.12306.cn/otn/confirmPassenger/confirmSingleForQueue';
                                                $confirmData = [
                                                    'passengerTicketStr' => "O,0,1,{$name},1,{$idCard},{$phone},N",
                                                    'oldPassengerStr' => "{$name},1,{$idCard},1_",
                                                    'randCode' => '',
                                                    'purpose_codes' => '00',
                                                    'key_check_isChange' => $ticketInfoForPassengerForm['key_check_isChange'],
                                                    'leftTicketStr' => $ticketInfoForPassengerForm['leftTicketStr'],
                                                    'train_location' => $ticketInfoForPassengerForm['train_location'],
                                                    'choose_seats' => '',
                                                    'seatDetailType' => '000',
                                                    'whatsSelect' => '1',
                                                    'roomType' => '00',
                                                    'dwAll' => 'N',
                                                    '_json_att' => '',
                                                ];
                                                $this->request($confirm, false, $confirmData, false, $cookieArray, $body);
                                                $this->info('看看什么返回 confirmSingleForQueue：' . $body);
                                                $confirmSingleForQueueRes = json_decode($body,true);
                                                if ($confirmSingleForQueueRes['httpstatus'] == 200 && $confirmSingleForQueueRes['status'] == true) {

                                                    exit(0);
                                                }

                                            }

                                        }

                                    }

                                }
                            }
                        }

//                         验证用户登录状态
//                        $checkUserLoginUrl = 'https://kyfw.12306.cn/otn/login/checkUser';
//                        todo 这块是用户登录验证
//                        $this->request($checkUserLoginUrl,false,['_json_att'=>''],false,$cookieArray,$body);
//                        $checkUserLoginResult = json_decode($body,true);
//                        if ($checkUserLoginResult['data']['flag'] == false) {
//
//                            $this->info('用户没有登录');
//                        } else {
//
//                            $this->info('用户已登录');
//                        }

                    }


                } else {

                    $this->error('登录错误:' . $loginJson['result_message']);
                }

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
