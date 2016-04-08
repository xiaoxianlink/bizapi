<?php
namespace Common\Model_new;
use Common\Model\CommonModel;
class FuwuModel extends CommonModel{
    protected $autoCheckFields =false;
    protected function _before_write(&$data) {
        parent::_before_write($data);
    }
    function percent($p,$t){
        return sprintf('%.2f%%',$p/$t*100);
    }
}