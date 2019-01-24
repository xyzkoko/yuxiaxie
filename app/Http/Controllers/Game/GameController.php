<?php

namespace App\Http\Controllers\game;

use App\model\UserInfo;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use App\Http\Controllers\user\UserController;
use App\Model\GameCards;
use App\Model\GameInfo;
use App\Model\Constant;
use App\Model\UserBet;
use App\Model\ResponseData;

class GameController extends Controller
{
    /*每三分钟运行一次进行游戏*/
    public function startGame()
    {
        set_time_limit(120);
        $idKey = "GAME_ID_YXX";       // 当局ID
        $hour = date('H');
        $minute = date('i');
        $num = intval(($hour * 60 + $minute) / 2) + 1;
        $num = sprintf("%03d", $num);       // 补齐3位
        $gameId = date('Ymd') . '|' . $num;
        Redis::set($idKey, $gameId);      // 更新当局ID
        // 准别阶段
        $gameKey = "GAME_INFO_YXX";       // 当局信息
        Redis::set($gameKey, json_encode(new GameInfo()));
        $gameInfo = json_decode(Redis::get($gameKey), true);
        $gameInfo['gameId'] = $gameId;
        $gameInfo['startTime'] = UserController::getMillisecond();
        $gameInfo['status'] = 0;
        Redis::set($gameKey, json_encode($gameInfo));            // 更新Redis
        sleep(100);      // 等待
        // 结算阶段
        $gameCards = GameCards::find($gameId);
        $gameInfo['dice'] = $gameCards["cards"];
        $gameInfo['status'] = 2;
        Redis::set($gameKey, json_encode($gameInfo));      // 更新Redis
        $gameCards->status = 2;
        $gameCards->save();
        return "success";
    }

    /*每天早上生成次日的牌组*/
    public function addGameList()
    {
        $data = date("Ymd", strtotime("+1 day"));
        $closeTime = strtotime($data);
        for ($i = 1; $i <= 720; $i++) {
            $gameCards = new GameCards;
            $gameCards->id = $data . '|' . sprintf("%03d", $i);       // 补齐3位;
            $gameCards->cards = json_encode(array(rand(1, 6), rand(1, 6), rand(1, 6)));
            $gameCards->close_time = $closeTime * 1000;
            $gameCards->save();
            $closeTime += 120;
        }
        echo 'success';
    }

    /*生成今天的牌组*/
    public function addTodayGameList()
    {
        $data = date("Ymd");
        $closeTime = strtotime($data);
        for ($i = 1; $i <= 720; $i++) {
            $gameCards = new GameCards;
            $gameCards->id = $data . '|' . sprintf("%03d", $i);       // 补齐3位;
            $gameCards->cards = json_encode(array(rand(1, 6), rand(1, 6), rand(1, 6)));
            $gameCards->close_time = $closeTime * 1000;
            $gameCards->save();
            $closeTime += 120;
        }
        echo 'success';
    }

    /*获取当前牌局信息*/
    public function getGameInfo()
    {
        $response = new ResponseData();
        $idKey = "GAME_ID_YXX";       // 当局ID
        $nextGameId = Redis::get($idKey);
        $pieces = explode("|", $nextGameId);
        if ($pieces[1] == "001") {
            $date = date('Ymd', strtotime("-1 day"));
            $num = 720;
        } else {
            $date = $pieces[0];
            $num = $pieces[1] - 1;
        }
        $num = sprintf("%03d", $num);       // 补齐3位
        $gameId = $date . '|' . $num;
        $gameCards = GameCards::find($gameId)->toArray();
        $dice = json_decode($gameCards['cards'],true);
        $data['dice'] = implode(",",$dice);
        $data['gameId'] = $gameId;
        $data['nextGameId'] = $nextGameId;
        $response->data = $data;
        return json_encode($response);
    }

}

