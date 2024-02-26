<?php

namespace Mihdan\IndexNow\Dependencies\Auryn;

interface ReflectionCache
{
    public function fetch($key);
    public function store($key, $data);
}
