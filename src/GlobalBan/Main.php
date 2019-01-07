<?php
namespace GlobalBan;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\CommandExecutor;
use pocketmine\utils\Utils;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\scheduler\CallbackTask;
    
class Main extends PluginBase implements Listener{

	public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder(), 0777, true);
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, array('Language' => 'en','LinkDefaultBan' => 'true','BanMessage' => 'You were banned by admin','RTAlert' => 'false','license' => null,'ChattingReciever' => 'true','muteid' => null));
        
        $this->getLogger()->notice($this->getMessage("Connecting to the GlobalBan Database...","グローバルBANデータベースに接続しています..."));
        
        $curl = curl_init("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=test&license=".$this->getLicense());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $database = json_decode(curl_exec($curl));
        
        if(empty($database)){
            $this->getLogger()->warning($this->getMessage("Could not Connect to the GlobalBan Database. If You Haven't Created Account Yet, You Must Create. You Already Have Key, Please Enter That in the Config 'license'","グローバルBANデータベースに接続できませんでした。アカウントを作っていない場合は先に作ってください。もし、すでに持っている場合はそれをConfigのlicenseに打ってください"));
            $this->getLogger()->notice($this->getMessage("Official GBan URL:","公式GbanサイトのURL: ")."http://korado531m7.php.xdomain.jp/gban");
            $this->getServer()->getPluginManager()->disablePlugin($this->getServer()->getPluginManager()->getPlugin("GlobalBans"));
        }else{
            if($database->{'status'} == "true"){
                $this->getLogger()->notice($this->getMessage("Connected to the GlobalBan Database","グローバルBANデータベースに接続しました"));
                Utils::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=statuspost&license=".$this->getLicense(), ["status" => "true","name" => $this->getServer()->getMotd(),"license" => $this->getLicense()]);
                
                $this->getLogger()->info($this->getMessage("Enabled GlobalBan Plugin and Turned on the GBan System","グローバルBANプラグインが有効になり、GBANが有効になりました"));
                $this->getLogger()->notice($this->getMessage("Using License: ","使用しているライセンス: ").$this->getLicense());
                $this->getLogger()->notice($this->getMessage("Official GBan URL:","公式GbanサイトのURL: ")."http://korado531m7.php.xdomain.jp/gban");
            }else{
                $this->getLogger()->notice($this->getMessage("Connected to the GlobalBan Database","グローバルBANデータベースに接続しました"));
                $this->getLogger()->alert($this->getMessage("Enabled GlobalBan Plugin but the GlobalBan Database is not Running","GlobalBan プラグインは有効になりましたが、データベースは稼働していません"));
                $this->getLogger()->info($this->getMessage("Recieved the Database Message:","データベースからメッセージを受信しました:")."\n§e".$database->{'message'});
                $this->getServer()->getPluginManager()->disablePlugin($this->getServer()->getPluginManager()->getPlugin("GlobalBans"));
            }
        }
        if($this->getServer()->getPluginManager()->getPlugin("GlobalChat")){
            $this->getLogger()->warning($this->getMessage('Please Remove GlobalChat Plugin or This','このプラグインかグローバルチャットを抜いてください'));
            $this->getServer()->forceShutdown();
        }
        $this->logo = "§a[GlobalBan] §f";
        if($this->config->get("RTAlert") == "true"){
            $this->set = json_decode(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=rt&license=".$this->getLicense()));
            $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "realTimeShower"]), 35);
        }
        if($this->config->get("ChattingReciever") == "true"){
            $this->gchat = json_decode(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=chatget&license=".$this->getLicense()));
            $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "chattingReciever"]), 35);
        }
    }
    
    public function onDisable(){
        Utils::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=statuspost&license=".$this->getLicense(), ["status" => "false", "license" => $this->getLicense()]);
    }
    
    private function getLicense(){
        return $this->config->get("license");
    }
    
    function chattingReciever(){
        if(empty(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=chatget&license=".$this->getLicense()))){
            return;
        }else{
            $chatobj = json_decode(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=chatget&license=".$this->getLicense()));
            if($this->gchat != $chatobj){
                foreach($this->config->get("muteid") as $chatid){
                    if($chatid["id"] == $chatobj->{'id'}){
                        $this->gchat = json_decode(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=chatget&license=".$this->getLicense()));
                        return;
                    }
                }
                $this->getLogger()->notice("§6+------------- ".$this->getMessage('Chatting System','チャットシステム')." -------------+");
                $this->getLogger()->notice("§b+".$this->getMessage('Server Name','サーバー名')." §7: §e".$chatobj->{'name'});
                $this->getLogger()->notice("§b+".$this->getMessage('Server ID','サーバーID')."   §7: §e".$chatobj->{'id'});
                $this->getLogger()->notice("§b+".$this->getMessage('Message','メッセージ')."     §7: §e".$chatobj->{'message'});
                $this->getLogger()->notice("§6+-------------------------------------------+");
                $this->gchat = json_decode(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=chatget&license=".$this->getLicense()));
            }
        }
    }
    
    function realTimeShower(){
        if(empty(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=rt&license=".$this->getLicense()))){
            return;
        }else{
            $db = json_decode(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=rt&license=".$this->getLicense()));
            if($this->set != $db){
                if($this->config->get("Language") == "jp"){
                    $this->getLogger()->notice("§6+------------- リアルタイム通知 -------------+");
                    $this->getLogger()->notice("§bBANしたサーバー名 : ".$db->{'ServerName'});
                    $this->getLogger()->notice("§bBANの種類         : ".$db->{'bantype'});
                    $this->getLogger()->notice("§bBANの内容      　 : ".$db->{'value'});
                    $this->getLogger()->notice("§bBANした理由   　  : ".$db->{'reason'});
                    $this->getLogger()->notice("§6+--------------------------------------------+");
                }else{
                    $this->getLogger()->notice("§6+--------- RealTime Notification ---------+");
                    $this->getLogger()->notice("§bPlayer Banned From : ".$db->{'ServerName'});
                    $this->getLogger()->notice("§bBanning Type       : ".$db->{'bantype'});
                    $this->getLogger()->notice("§bBanned Value       : ".$db->{'value'});
                    $this->getLogger()->notice("§bBanned Reason      : ".$db->{'reason'});
                    $this->getLogger()->notice("§6+-----------------------------------------+");
                }
                $this->set = json_decode(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=rt&license=".$this->getLicense()));
            }
        }
    }
    
    function getMessage($en,$jp){
        if($this->config->get("Language") == "jp"){
            return $jp;
        }else{
            return $en;
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        switch($command->getName()){
            case "gban":
                if(empty($args[0])){
                    $args[0] = false;
                }
                switch(strtolower($args[0])){
                      
                    default:
                        $sender->sendMessage($this->getMessage("§6----------- GlobalBan Command -----------\n§e/gban <Name> <Player Name> <Description>\n§e/gban <Cid> <Client ID> <Description>\n§e/gban <Ip> <IP Address> <Description>\n§e/gban <All> <Player Name> <Description>\n§e/gban <List> <Name/Ip/Cid>\n§e/gban <Status>\n§e/chat <Server ID> <Message>\n§e/chatmute <Server ID>\n§6--------------------------------------------","§6----------- GlobalBan コマンド -----------\n§e/gban <Name> <プレイヤー名> <BANの説明>\n§e/gban <Cid> <Client ID> <BANの説明>\n§e/gban <Ip> <IPアドレス> <BANの説明>\n§e/gban <All> <プレイヤー名> <BANの説明>\n§e/gban <List> <Name/Ip/Cid>\n§e/gban <Status>\n§e/chat <サーバーID> <メッセージ>\n§e/chatmute <サーバーID>\n§6--------------------------------------------"));
                    break;
                        
                    case "list":
                        if(empty($args[1])){
                            $sender->sendMessage($this->getMessage("§9> §e/gban <List> <Name/Ip/Cid>","§9> §e/gban <List> <Name/Ip/Cid>"));
                        }else{
                            if(strtolower($args[1]) == "name" || strtolower($args[1]) == "ip" || strtolower($args[1]) == "cid"){
                                $this->getLogger()->notice($this->getMessage("Loading Database...","データベースを読み込み中..."));
                                $datalist = file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=list&type=".strtolower($args[1])."&license=".$this->getLicense());
                                //$result = "Reason: ".$datalist->{'reason'}." , Banned Server: ".$datalist->{'server'}." , Banned Type: ".$datalist->{'type'};
                                $this->getLogger()->info("+---------------------- Banned List ---------------------+\n".$this->getMessage($datalist,str_replace(array("Reason:","Banned Server:","Banned Type:","No Description"),array("理由:","BANされたサーバー:","BANの種類:","理由なし"),$datalist)));
                                //$this->getLogger()->info("+---------------------- Banned List ---------------------+\n".$result);
                                $this->getLogger()->info("+--------------------------------------------------------+");
                            }else{
                                $sender->sendMessage($this->getMessage("§9> §e/gban <List> <Name/Ip/Cid>","§9> §e/gban <List> <Name/Ip/Cid>"));
                            }
                        }
                    break;
                        
                    /*
                    case "rid":
                        if(empty($args[1])){
                            $sender->sendMessage($this->getMessage("§9> §e/gban <Rid> <Player Name/RawUniqueId> <Description>","§9> §e/gban <Rid> <プレイヤー名/RawUniqueID> <BANの説明>"));
                        }else{
                            if(empty($args[2])){
                                $args[2] = "No Description";
                            }
                            
                            if(is_null($this->getServer()->getPlayer($args[1]))){
                                    $name = $args[1];
                                    array_shift($args);
                                    array_shift($args);
                                    Utils::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "rid","value" => $name,"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                    $datum = strtolower($name);
                            }else{
                                $name = $args[1];
                                array_shift($args);
                                array_shift($args);
                                Utils::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "rid","value" => $this->getServer()->getPlayer($name)->getRawUniqueId(),"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                $datum = $this->getServer()->getPlayer($name)->getRawUniqueId();
                                $this->getServer()->getPlayer($name)->kick($this->config->get("BanMessage"),false);
                            }
                            $sender->sendMessage($this->logo.$this->getMessage("§9> §dRawUniqueID Ban Data has been posted","§9> §dRawUniqueID Banのデータが送信されました"));
                            $this->getLogger()->notice($this->getMessage("§9> §bRawUniqueID Ban Data Has Been Posted. Posted Client ID:","§9> §bRawUniqueID BANデータが送信されました。 送信されたID:").$datum);
                        }
                    break;
                    */
                    
                    case "chat":
                        $sender->sendMessage($this->getMessage("§9> §e/chat <Server ID> <Message>\n§9> §e/chatmute <Server ID>","§9> §e/chatmute <サーバーID>"));
                    break;
                    
                    case "status":
                        if($sender instanceof Player){
                            $sender->sendMessage($this->getMessage("§9> §dThis Command Can Use On Console Only","§9> §dこのコマンドはコンソール専用になっています"));
                            return;
                        }
                        $this->getLogger()->notice($this->getMessage("Getting Status from Database...","データベースから状況を取得中..."));
                        $listdata = file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=serverstatus&license=".$this->getLicense());
                        $this->getLogger()->info("+--------------------- Online Servers --------------------+\n".$this->getMessage($listdata,str_replace(array("STATUS:","SERVER ID:"),array("状況:","サーバーID:"),$listdata)));
                        $this->getLogger()->info("+---------------------------------------------------------+");
                    break;
                        
                    case "cid":
                        if(empty($args[1])){
                            $sender->sendMessage($this->getMessage("§9> §e/gban <Cid> <Client ID> <Description>","§9> §e/gban <Cid> <クライアントID> <BANの説明>"));
                        }else{
                                if(empty($args[2])){
                                    $args[2] = "No Description";
                                }
                            
                            if(is_null($this->getServer()->getPlayer($args[1]))){
                                if(preg_match("/^[0-9]+$/", $args[1])){
                                    $name = $args[1];
                                    array_shift($args);
                                    array_shift($args);
                                    Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "cid","value" => $name,"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                    $datum = strtolower($name);
                                }else{
                                    $sender->sendMessage($this->getMessage("§9> §dCliend ID must be an integer","§9> §dクライアントIDは数字でなければいけません"));
                                    return;
                                }
                            }else{
                                $name = $args[1];
                                array_shift($args);
                                array_shift($args);
                                Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "cid","value" => $this->getServer()->getPlayer($name)->getClientId(),"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                $datum = $this->getServer()->getPlayer($name)->getClientId();
                                $this->getServer()->getPlayer($name)->kick($this->config->get("BanMessage"),false);
                            }
                            $sender->sendMessage($this->logo.$this->getMessage("§9> §dClient Ban Data has been posted","§9> §dクライアントBanのデータが送信されました"));
                            $this->getLogger()->notice($this->getMessage("§9> §bClient Ban Data Has Been Posted. Posted Client ID:","§9> §bクライアントBANデータが送信されました。 送信されたID:").$datum);
                        }
                        break;
                        
                        case "all":
                            if(empty($args[1])){
                                $sender->sendMessage($this->getMessage("§9> §e/gban <All> <Player Name> <Description>","§9> §e/gban <All> <プレイヤー名> <BANの説明>"));
                            }else{
                                if(empty($args[2])){
                                    $args[2] = "No Description";
                                }
                                if(is_null($this->getServer()->getPlayer($args[1]))){
                                    $sender->sendMessage($this->logo.$this->getMessage("§9> §dPlayer ".$args[1]." is not online now.","§9> §dプレイヤー ".$args[1]." は今オンラインではありません"));
                                }else{
                                    $name = $args[1];
                                    $p = $this->getServer()->getPlayer($name);
                                    array_shift($args);
                                    array_shift($args);
                                    
                                    Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "cid","value" => $p->getClientId(),"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                    Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "ip","value" => $p->getAddress(),"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                    Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "name","value" => $p->getName(),"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                    
                                    if($this->config->get("LinkDefaultBan") == "true"){
                                        $this->getServer()->getIPBans()->addBan($datum, implode(" ",$args), null, "GlobalBan Plugin");
                                        $this->getServer()->getNameBans()->addBan($datum, implode(" ",$args), null, "GlobalBan Plugin");
                                    }
                                    
                                    
                                    $p->kick($this->config->get("BanMessage"),false);
                                    $sender->sendMessage($this->logo.$this->getMessage("§9> §dIP, Client ID, Name Ban Data has been posted","§9> §dIP, クライアントID, 名前Banのデータが送信されました"));
                                    $this->getLogger()->notice($this->getMessage("§9> §bPosted Data. IP: ".$p->getAddress()." | ClientID: ".$p->getClientId()." | Name: ","§9> §b送信されたデータ IP: ".$p->getAddress()." | クライアントID: ".$p->getClientId()." | 名前: ").$p->getName());
                                }
                            }
                        break;
                        
                        case "ip":
                        if(empty($args[1])){
                            $sender->sendMessage($this->logo.$this->getMessage("§9> §e/gban <Ip> <IP Address> <Description>","§9> §e/gban <Ip> <IPアドレス> <BANの説明>"));
                        }else{
                            if(empty($args[2])){
                                $args[2] = "No Description";
                            }
                            if(is_null($this->getServer()->getPlayer($args[1]))){
                                if(preg_match("/^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/", $args[1])){
                                    $name = $args[1];
                                    array_shift($args);
                                    array_shift($args);
                                    Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "ip","value" => $name,"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                }else{
                                    $sender->sendMessage($this->getMessage("§9> §dType the Correct IP Address","§9> §d正しいIPアドレスを入力してください"));
                                    return;
                                }
                                $datum = strtolower($name);
                            }else{
                                $name = $args[1];
                                array_shift($args);
                                array_shift($args);
                                Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "ip","value" => $this->getServer()->getPlayer($name)->getAddress(),"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                $datum = $this->getServer()->getPlayer($name)->getAddress();
                                $this->getServer()->getPlayer($name)->kick($this->config->get("BanMessage"),false);
                            }
                            if($this->config->get("LinkDefaultBan") == "true"){
                                $this->getServer()->getIPBans()->addBan($datum, implode(" ",$args), null, "GlobalBan Plugin");
                            }
                                $sender->sendMessage($this->logo.$this->getMessage("§9> §dIP Ban Data has been posted","§9> §dIP Banのデータが送信されました"));
                                $this->getLogger()->notice($this->getMessage("§9> §bIP Ban Data Has Been Posted. Posted IP Address:","§9> §bIP BANデータが送信されました。 送信されたIPアドレス:").$datum);
                        }
                        break;
                        
                    case "name":
                        if(empty($args[1])){
                            $sender->sendMessage($this->logo.$this->getMessage("§9> §e/gban <Name> <Player Name> <Description>","§9> §e/gban <Name> <プレイヤー名> <説明>"));
                        }else{
                            if(empty($args[2])){
                                $args[2] = "No Description";
                            }
                            if(is_null($this->getServer()->getPlayer($args[1]))){
                                $name = $args[1];
                                array_shift($args);
                                array_shift($args);
                                Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "name","value" => $name,"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                $datum = strtolower($name);
                            }else{
                                $name = $args[1];
                                array_shift($args);
                                array_shift($args);
                                Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=post&license=".$this->getLicense(), ["type" => "name","value" => $this->getServer()->getPlayer($name)->getName(),"servername" => $this->getServer()->getMotd(),"description" => implode(" ",$args),"license" => $this->getLicense()]);
                                $datum = $this->getServer()->getPlayer($name)->getName();
                                $this->getServer()->getPlayer($name)->kick($this->config->get("BanMessage"),false);
                            }
                            if($this->config->get("LinkDefaultBan") == "true"){
                                $this->getServer()->getNameBans()->addBan($datum, implode(" ",$args), null, "GlobalBan Plugin");
                            }
                            $sender->sendMessage($this->logo.$this->getMessage("§9> §dName Ban Data has been posted","§9> §d名前 Banのデータが送信されました"));
                            $this->getLogger()->notice($this->getMessage("§9> §bName Ban Data Has Been Posted. Posted Minecraft Name:","§9> §b名前 BANデータが送信されました。 送信されたマインクラフト名:").$datum);
                        }
                        break;
                }
                break;
                
            case "chatmute":
                if(empty($args[0])){
                    $sender->sendMessage($this->getMessage("§9> §e/chatmute <Server ID>","§9> §e/chatmute <サーバーID>"));
                }else{
                    if($sender instanceof Player){
                        $sender->sendMessage($this->getMessage("§9> §dThis Command Can Use On Console","§9> §dこのコマンドはコンソール上からのみ可能です>"));
                    }else{
                        $tmp = array();
                        $tmp["id"] = $args[0];
                        $this->muteids[] = $tmp;
                        $this->config->set("muteid",$this->muteids);
                        $this->config->save();
                        $sender->sendMessage($this->getMessage("§9> §aServer ID §e".$args[0]."§a Has Been Muted","§9> §aサーバー ID §e".$args[0]."§a をミュートしました"));
                    }
                }
            break;
                
            case "chat":
                if(empty($args[1])){
                    $sender->sendMessage($this->getMessage("§9> §e/chat <Server ID> <Message>","§9> §e/chat <サーバーID> <メッセージ>"));
                }else{
                    if($sender instanceof Player){
                        $sender->sendMessage($this->getMessage("§9> §dThis Command Can Use On Console","§9> §dこのコマンドはコンソール上からのみ可能です>"));
                    }else{
                        $servid = $args[0];
                        $chat = json_decode(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=statusget&serverid=".$servid."&license=".$this->getLicense()));
                        array_shift($args);
                        if($chat->{'settings'} == "false"){
                            $sender->sendMessage($this->getMessage("§9> §dChat Partner Selecting to Rejecting Mode, So Message Couldn't Send","§9> §dチャット相手は拒否モードにしているためチャットを送信できませんでした"));
                            return;
                        }
                        
                        if($chat->{'active'} == "true"){
                            if($chat->{'status'} == "true"){
                                Internet::postURL("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=chatpost&serverid=".$servid."&license=".$this->getLicense(), ["message" => implode(" ",$args),"name" => $this->getServer()->getMotd(),"license" => $this->getLicense()]);
                                $sender->sendMessage($this->getMessage("§9> §aChat Sent Successful","§9> §aチャットが送信されました"));
                            }else{
                                $sender->sendMessage($this->getMessage("§9> §aChat Couldn't Send, Because Partner Is Not Online Now","§9> §aチャット相手はオンラインではないため、送信できませんでした"));
                            }
                        }else{
                            $sender->sendMessage($this->getMessage("§9> §dThat Server ID Is Not Registered Yet","§9> §dそのサーバーIDは未登録です"));
                        }
                    }
                }
                break;
        }
    }
    
    public function checkPlayer(PlayerPreLoginEvent $ev){
        $player = $ev->getPlayer();
        $db = json_decode(file_get_contents("http://korado531m7.php.xdomain.jp/gban/gbansystem.php?sys=login&name=".strtolower($player->getName())."&ip=".$player->getAddress()."&cid=".$player->getClientId()."&license=".$this->getLicense()));
        if($db->{'ip'} == "true"){
            $player->close("",str_replace("%n","\n",$db->{'ipreason'}));
            $ev->setCancelled(true);
        }elseif($db->{'cid'} == "true"){
            $player->close("",str_replace("%n","\n",$db->{'cidreason'}));
            $ev->setCancelled(true);
        }elseif($db->{'name'} == "true"){
            $player->close("",str_replace("%n","\n",$db->{'namereason'}));
            $ev->setCancelled(true);
        }
    }
}
 
