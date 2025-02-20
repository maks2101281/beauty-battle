<?php
class RateLimiter {
    private $redis;
    private $maxRequests = 100;
    private $timeWindow = 3600;

    public function __construct() {
        $this->redis = new Redis();
        $this->redis->connect(Env::get('REDIS_HOST'), Env::get('REDIS_PORT'));
    }

    public function check($ip) {
        $key = "rate_limit:{$ip}";
        $requests = $this->redis->get($key) ?: 0;
        
        if ($requests >= $this->maxRequests) {
            return false;
        }
        
        $this->redis->incr($key);
        $this->redis->expire($key, $this->timeWindow);
        
        return true;
    }
} 