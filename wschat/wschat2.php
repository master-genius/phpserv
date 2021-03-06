<?php
class wsChat
{
    private $server;
    private $server_pid;
    private $auth_cache;
    private $sock_head = 'user_sock_';

    function __construct()
    {
        $this->server_pid = posix_getpid();
        $this->auth_cache = new Memcached('auth');
        $this->auth_cache->addServer('localhost',11211);
        $this->server = new swoole_websocket_server('localhost',7654);
        $this->server->set([
            'daemonize' => 1
        ]);
    }

    //get username by token_$fd
    private function getUser($fd) {
        $token = $this->auth_cache->get('token_'.$fd);
        return $this->auth_cache->get($token);
    }

    public function on_message($server, $req) {
        $data = json_decode($req->data,true);
        $msg = (isset($data['msg'])?$data['msg']:'');
        if (empty($msg)) {
            return ;
        }
        //check if logout
        if ($msg=='//logout') {
            $this->logout($req->fd);
            $server->close($req->fd);
            return ;
        }

        $send_msg = [
            'from'=>$this->getUser($req->fd),
            'msg'=>$msg,
            'time'=>time(),
        ];
        $keys = $this->mcache->getAllKeys();
        $this->mcache->getDelayed($keys);
        $key_vals = $this->mcache->fetchAll();
        foreach ($key_vals as $kv) {
            if ($kv['value']==$req->fd) {
                continue;
            }
            $server->push($kv['value'],json_encode($send_msg));
        }
    }

    public function on_shutdown($server) {
        $this->mcache->deleteMulti($this->mcache->getAllKeys());
        $this->mcache->quit();
        $this->auth_cache->deleteMulti($this->auth_cache->getAllKeys());
        $this->auth_cache->quit();
    }

    public function on_open($server, $req) {
        if (!$this->auth_cache->get($req->get['user_token'])) {
            $server->push($req->fd,json_encode([
                'msg_source'=>'server',
                'msg'=>'you need to login.'.$req->get['user_token'],
                'error'=>-1
            ]));
            $server->close($req->fd);
        }
        $this->mcache->set($this->sock_head.$req->fd, $req->fd);
        //关联token和连接
        $this->auth_cache->set('token_'.$req->fd, $req->get['user_token']);
        $sys_msg = [
            'msg_source'=>'server',
            'msg'=>'you are login at '.$req->fd
        ];

        $server->push($req->fd,json_encode($sys_msg));
    }

    public function on_close($server,$fd) {
        $this->mcache->delete($this->sock_head.$fd,0);
        $this->auth_cache->delete('token_'.$fd,0);
    }

    public function logout($fd) {
        $username = $this->getUser($fd);
        //通过token和连接套接字的关联获取user_token并删除
        $this->auth_cache->delete($this->auth_cache->get('token_'.$fd),0);
        $this->auth_cache->delete($username,0);
    }

    public function start_server() {
        $this->server->on('open',[$this,'on_open']);

        $this->server->on('message',[$this,'on_message']);
        
        $this->server->on('close',[$this,'on_close']);

        $this->server->on('shutdown',[$this,'on_shutdown']);
        
        $this->server->start();
    }
}

(new wsChat())->start_server();

