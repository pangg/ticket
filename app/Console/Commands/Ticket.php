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

    protected $siteType = [
        1 => [
            'index' => 1,
            'name' => '硬座',
            'value' => '1',
            'key' => 'yz',
        ],
        2 => [
            'index' => 2,
            'name' => '硬卧',
            'value' => '3',
            'key' => 'yw',

        ],
        3 => [
            'index' => 3,
            'name' => '软卧',
            'value' => '4',
            'key' => 'rw',
        ],
        4 => [
            'index' => 4,
            'name' => '商务座',
            'value' => '9',
            'key' => 'swz',
        ],
        5 => [
            'index' => 5,
            'name' => '一等座',
            'value' => 'M',
            'key' => 'ydz',
        ],
        6 => [
            'index' => 6,
            'name' => '二等座',
            'value' => 'O',
            'key' => 'edz',
        ],
    ];


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
        return parent::ask($question, $default);
    }

    public function info($string, $verbosity = null)
    {
        parent::info("\e[34m " . $string . " \e[34m", $verbosity);
    }

    public function error($string, $verbosity = null)
    {
        parent::error("\e[31m " . $string . " \e[31m", $verbosity);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $username = $this->ask('12306 用户名');
        $password = $this->ask('12306 密码');
        $cookieArray = [];
        $head = '';
        $body = '';
        $train_date = $this->ask('请输入去程日期 格式：' . date('Y-m-d'));
        $back_train_date = $this->ask('请输入反程日期 格式：' . date('Y-m-d'));
//        $train_date = '2018-02-10';
//        $back_train_date = '2018-02-10';
        $from_name = '';
        $from_code = '';
        $to_name = '';
        $to_code = '';
        while (true) {

            $from_name = $this->ask('从哪里出发?');
//            $from_name = '深圳';
            $v = Config::get('ticket.address.' . $from_name);
            if ($v != null) {

                $from_code = $v;
                break;
            }
            $this->error('address is error');
        }
        while (true) {

            $to_name = $this->ask('到哪里去?');
//            $to_name = '龙南';
            $v = Config::get('ticket.address.' . $to_name);
            if ($v != null) {

                $to_code = $v;
                break;
            }
            $this->error('address is error');
        }
        $ticketInfoForPassengerForm = '';

        if ($this->checkUser($username, $password, $cookieArray)) {

            //查询车次
            tripsFind:
            $this->request('https://kyfw.12306.cn/otn/leftTicket/queryZ', true, [
                'leftTicketDTO.train_date' => $train_date,
                'leftTicketDTO.from_station' => $from_code,
                'leftTicketDTO.to_station' => $to_code,
                'purpose_codes' => 'ADULT'
            ], false, $cookieArray, $body, $head);
            $tripsJson = json_decode($body, true);
            if ($tripsJson['httpstatus'] == 200) {

                $headCol = [
                    '序号',
                    '车次',
                    '出发时间',
                    '到达时间',
                    '历时',
                    '商务座/特等座',
                    '一等座',
                    '二等座',
                    '高级软卧',
                    '软卧',
                    '动卧',
                    '硬卧',
                    '软座',
                    '硬座',
                    '无座',
                    '其他',
                    '是否可订票',
                ];

                $tripsResult = [];
                $tripsResultIndex = 0;
                //整理结果集 begin
                foreach ($tripsJson['data']['result'] as $item) {

                    $d = explode('|', $item);
                    $tripsResultIndex++;
                    $tripsResult[] = [
                        'index' => $tripsResultIndex,
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
                    ];

                }
                $this->info('已为你查询到以下车次');
                $this->table($headCol, $tripsResult);
                $tripsIndex = $this->ask('请选择有效车次序号!');
                if (!isset($tripsResult[$tripsIndex - 1])) {

                    $this->error('序号不存在请重试!');
                    goto tripsFind;
                }
                //选定车次
                $tripsCode = $tripsResult[$tripsIndex - 1]['cc'];

            } else {

                goto tripsFind;
            }
            $this->info("您选择的车次是：{$tripsCode}");
            selectSiteIndex:
            $this->table(['序号', '座位类型', '座位字段', '座位索引'], $this->siteType);
            $siteIndex = $this->ask('请选择座位类型 请对应车次类型');
            if (array_key_exists($siteIndex, $this->siteType)) {

                $site_type = $this->siteType[$siteIndex]['value'];
                $site_key = $this->siteType[$siteIndex]['key'];
            } else {

                $this->error('选择错误,请重新选择');
                goto selectSiteIndex;
            }
            $this->info("site_type : {$site_type} site_key: {$site_key}");
            //获取/确认乘车人
            GetPassenger:
            $passengersUrl = 'https://kyfw.12306.cn/otn/passengers/init';
            $this->request($passengersUrl, false, ['_json_att' => ''], false, $cookieArray, $body, $head);
            if ($body == '') {
                goto GetPassenger;
            }
            preg_match('/passengers=\[.*\];/', $body, $mth);
            $passengerObj = str_replace("passengers=", '', $mth[0]);
            $passengerObj = str_replace(";", '', $passengerObj);
            $passengerJsonStr = str_replace("'", '"', $passengerObj);
            $passengerJson = json_decode($passengerJsonStr, true);
            ShowUserList:
            $passengerTicketStr = '';
            $oldPassengerStr = '';
            $passengerRes = [];
            $passengerResIndex = 0;
            foreach ($passengerJson as $item) {

                $passengerResIndex++;
                $headCol = [
                    '序号',
                    '姓名',
                    '身份证号',
                    '手机号',
                ];
                $passengerRes[] = [
                    'index' => $passengerResIndex,
                    'name' => $item['passenger_name'],
                    'idCard' => $item['passenger_id_no'],
                    'phone' => $item['mobile_no'],
                ];

            }
            $this->table($headCol, $passengerRes);
            $userList = $this->ask('请选择乘车人 如果多人请以英文逗号分隔 ","!');
            $userArray = explode(',', $userList);
            foreach ($userArray as $value) {

                if (!isset($passengerRes[$value - 1])) {

                    $this->error('选择错误 请重新选择');
                    goto ShowUserList;
                }

                $passengerTicketStr .= $site_type . ",0,{$passengerJson[$value - 1]['passenger_type']},{$passengerJson[$value - 1]['passenger_name']},{$passengerJson[$value - 1]['passenger_id_type_code']},{$passengerJson[$value - 1]['passenger_id_no']},{$passengerJson[$value - 1]['mobile_no']},N_";
                $oldPassengerStr .= "{$passengerJson[$value - 1]['passenger_name']},{$passengerJson[$value - 1]['passenger_id_type_code']},{$passengerJson[$value - 1]['passenger_id_no']},1_";
            }
            //裁切 N_
            $passengerTicketStr = substr($passengerTicketStr, 0, strlen($passengerTicketStr) - 1);
            $this->info('$passengerTicketStr:' . $passengerTicketStr);
            $this->info('$oldPassengerStr:' . $oldPassengerStr);
            $this->info('进入主循环查询');
            while (true) {
                //循环查询
                sleep(2);
                $queryZ = 'https://kyfw.12306.cn/otn/leftTicket/queryZ';

                $queryData = [
                    'leftTicketDTO.train_date' => $train_date,
                    'leftTicketDTO.from_station' => $from_code,
                    'leftTicketDTO.to_station' => $to_code,
                    'purpose_codes' => 'ADULT'
                ];
                $body = '';
                $this->request($queryZ, true, $queryData, false, $cookieArray, $body, $head);
                $queryJson = json_decode($body, true);
                if ($queryJson['httpstatus'] == 200) {

                    $headCol = [
                        '序号',
                        '车次',
                        '出发时间',
                        '到达时间',
                        '历时',
                        '商务座/特等座',
                        '一等座',
                        '二等座',
                        '高级软卧',
                        '软卧',
                        '动卧',
                        '硬卧',
                        '软座',
                        '硬座',
                        '无座',
                        '其他',
                        '是否可订票',
                        '车次标记',
                    ];
                    $result = [];
                    $resultIndex = 0;
                    //整理结果集 begin
                    foreach ($queryJson['data']['result'] as $item) {

                        $d = explode('|', $item);
                        $resultIndex++;
                        $result[] = [
                            'index' => $resultIndex,
                            'cc' => $d[3],  //车次
                            'cf' => $d[8],  //出发时间
                            'dd' => $d[9],  //到达时间
                            'ls' => $d[10], //历时
                            'swz' => ($d[32] == '' || $d[32] == '无' || $d[32] == 0) ? '无' : '有', //商务座/特等座 ok
                            'ydz' => ($d[31] == '' || $d[31] == '无' || $d[31] == 0) ? '无' : '有', //一等座 ok
                            'edz' => ($d[30] == '' || $d[31] == '无' || $d[30] == 0) ? '无' : '有', //二等座 ok
                            'gjrw' => ($d[21] == '' || $d[21] == '无' || $d[21] == 0) ? '无' : '有', //高级软卧 21
                            'rw' => ($d[23] == '' || $d[23] == '无' || $d[23] == 0) ? '无' : '有', //软卧 ok
                            'dw' => ($d[33] == '' || $d[33] == '无' || $d[33] == 0) ? '无' : '有', //动卧 ok
                            'yw' => ($d[28] == '' || $d[28] == '无' || $d[28] == 0) ? '无' : '有', //硬卧 ok
                            'rz' => $d[3],  //软座
                            'yz' => ($d[29] == '' || $d[29] == '无' || $d[29] == 0) ? '无' : '有', //硬座 ok
                            'wz' => ($d[26] == '' || $d[26] == '无' || $d[26] == 0) ? '无' : '有', //无座 ok
                            'qt' => ($d[22] == '' || $d[22] == '无' || $d[22] == 0) ? '无' : '有', //其他 ok
                            'ok' => $d[0] == '' ? false : true, //是否可订票 ok
                            'secretStr' => urldecode($d[0])     //当前车次标记
                        ];

                    }
                    //整理结果集 end
                    $this->table($headCol, $result);

                    foreach ($result as $item) {

                        // 如果存在车票 车次匹配 座位匹配
                        if ($item['ok'] && $item['cc'] == $tripsCode && $item[$site_key] === '有') {

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

                            $this->request($submitOrderRequestUrl, false, $submitOrderRequestData, false, $cookieArray, $body, $head);
                            $submitOrderJson = json_decode($body, true);
                            if ($submitOrderJson['httpstatus'] == 200 && $submitOrderJson['status'] == true) {

                                globalRepeatSubmitToken:
                                $this->info('请求订单成功 : submitOrderRequest');
                                $initDc = 'https://kyfw.12306.cn/otn/confirmPassenger/initDc';
                                $this->request($initDc, false, ['_json_att' => ''], true, $cookieArray, $body, $head);
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
                                    'passengerTicketStr' => $passengerTicketStr,
                                    'oldPassengerStr' => $oldPassengerStr,
                                    'tour_flag' => 'dc',                                //dc 单程 wc 往返
                                    'randCode' => '',                                   //默认空
                                    'whatsSelect' => '1',                               //暂时默认为1
                                    '_json_att' => '',                                  //默认空
                                    'REPEAT_SUBMIT_TOKEN' => $repeatSubmitToken,        //
                                ];
                                $this->info('开始请求验证订单 : checkOrderInfo');
                                $this->request($checkOrderInfoUrl, false, $checkOrderInfoData, false, $cookieArray, $body, $head);
                                $checkOrderInfoDataJson = json_decode($body, true);
                                if ($checkOrderInfoDataJson['httpstatus'] == 200 && $checkOrderInfoDataJson['status'] == true) {

                                    $this->info('验证订单返回成功');
                                    $confirm = 'https://kyfw.12306.cn/otn/confirmPassenger/confirmSingleForQueue';
                                    $confirmData = [
                                        'passengerTicketStr' => $passengerTicketStr,
                                        'oldPassengerStr' => $oldPassengerStr,
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
                                    $this->request($confirm, false, $confirmData, false, $cookieArray, $body, $head);
                                    $confirmSingleForQueueRes = json_decode($body, true);
                                    if ($confirmSingleForQueueRes['httpstatus'] == 200 && $confirmSingleForQueueRes['status'] == true) {

                                        $this->info('订单完成 : confirmSingleForQueue');
                                        exit(0);
                                    }

                                }

                            }

                        }

                    }
                }
                continue;
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

            curl_close($curl);
            $body = '';
            $head = '';
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


    private function checkUser(string $username, string $password, array &$cookieArray)
    {
        Check:
        //验证用户登录状态
        $checkUserLoginUrl = 'https://kyfw.12306.cn/otn/login/checkUser';
        //todo 这块是用户登录验证
        $body = '';
        $head = '';
        $this->request($checkUserLoginUrl, false, ['_json_att' => ''], false, $cookieArray, $body, $head);
        $checkUserLoginResult = json_decode($body, true);
        if ($checkUserLoginResult['data']['flag'] == false) {

            $this->info('用户没有登录');
            $loginPost = 'https://kyfw.12306.cn/passport/web/login';
            $initUrl = 'https://kyfw.12306.cn/otn/login/init';
            $this->request($initUrl, true, [], false, $cookieArray, $body, $head);
            Yan:
            $yanUrl = 'https://kyfw.12306.cn/passport/captcha/captcha-image?login_site=E&module=login&rand=sjrand&' . mt_rand(0, 999);
            while (true) {

                $this->request($yanUrl, true, [], false, $cookieArray, $body, $head);
                if ($body !== '') {

                    break;
                }
            }
            $md5 = md5($body);

            $date = date('Y-m-d', time());
            $baseDir = "/uploads/img/{$date}/";

            $dir = app()->publicPath() . $baseDir;
            if (!is_dir($dir)) {

                mkdir($dir, 0777, true);
            }
            $has_rand = RandCode::where('md5', '=', $md5)->first();
            $randCode = null;
            if ($has_rand == null) {

                $this->info('未能在数据库中找到答案,请手动输入');

                $file = "{$dir}{$md5}.jpeg";
                $f = fopen($file, 'w+');
                $img = $body;
                fwrite($f, $img);
                fclose($f);
                $randCode = new RandCode();
                $randCode->md5 = $md5;
                $randCode->value = '';
                $randCode->path = $baseDir . "{$md5}.jpeg";
                $randCode->times = 0;
                $randCode->save();
                $this->info('请打开页面' . URL::to('/image') . '/' . $randCode->id);
                $lng = $this->ask('请输入图片坐标?');
            } else {

                if ($has_rand->value == '') {

                    $this->error('数据库存在,但是没有答案');
                    $this->info('请打开页面' . URL::to('/image') . '/' . $has_rand->id);
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
            $this->request($checkYan, false, $checkData, false, $cookieArray, $body, $head);
            $json = json_decode($body, true);
            $this->info('验证码返回' . $body);
            if ($json['result_code'] != "4") {

                unset($cookieArray['_passport_session']);
                unset($cookieArray['_passport_ct']);
                $this->info($json['result_message']);
                $this->info('准备重新获取验证码');
                goto Yan;
            } else {

                if ($has_rand == null) {

                    $randCode->value = $lng;
                    $randCode->save();

                } else {

                    $has_rand->value = $lng;
                    $has_rand->save();
                }

                $this->info('验证码通过...');

                $this->info('开始请求登录...');

                $loginData = [
                    'username' => $username,
                    'password' => $password,
                    'appid' => 'otn'
                ];
                $body = '';
                LoginPost:
                $this->request($loginPost, false, $loginData, false, $cookieArray, $body, $head);

                if ($body == '') {

                    goto LoginPost;
                }
                $this->info('请求登录返回 ' . $body);
                $loginJson = json_decode($body, true);
                if ($loginJson['result_code'] == 0) {

                    $cookieArray['uamtk'] = $loginJson['uamtk'];
                    $this->info('登录成功');
                    $uamtkUrl = 'https://kyfw.12306.cn/passport/web/auth/uamtk';
                    $this->request($uamtkUrl, false, ['appid' => 'otn'], false, $cookieArray, $body, $head);
                    $this->info('登录 uamtk: ' . $body);
                    $uamtkJson = json_decode($body, true);
                    if ($uamtkJson['result_code'] == 0) {

                        $tk = $uamtkJson['newapptk'];

                        uamauthclient:
                        $uamtkClientUrl = 'https://kyfw.12306.cn/otn/uamauthclient';
                        $this->request($uamtkClientUrl, false, ['tk' => $tk], false, $cookieArray, $body, $head);
                        $this->info('uamtkclient');
                        if ($body == '') {

                            goto uamauthclient;
                        }
                        $uamtkClientJson = json_decode($body, true);
                        if ($uamtkClientJson['result_code'] == 0) {

                            return true;
                        }
                    }
                    goto Check;
                } else {

                    $this->error('登录错误 请检查用户名密码');
                    return false;
                }

            }

        } else {

            $this->info('用户已登录');
            return true;
        }
    }
}
