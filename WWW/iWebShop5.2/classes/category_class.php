<?php
/**
 * @copyright (c) 2018 aircheng.com
 * @file category_class.php
 * @brief 商品分类
 * @author qfsoft
 * @date 2018-08-15 23:00:02
 * @version 5.2
 */
class category_class
{
    // 原始分类数据
    private $category_data = array();
    
    // 分类路径
    private $category_path = array();
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $categoryObj = new IModel('category');
        $this->category_data = $categoryObj->query(false, 'id,name,parent_id', 'sort asc');
    }
    
    /**
     * @brief 获取分类路径
     */
    public function get_path($id)
    {
        // 初始化分类路径
        $this->category_path = array();
        $this->get_parent_id($id);
        return $this->category_path;
    }
    
    /**
     * @brief 获取上一级分类id
     */
    public function get_parent_id($id)
    {
        if (empty($id)) {
            return false;
        }
        foreach ($this->category_data as $val)
        {
            if ($id == $val['id'])
            {
                // 去掉parent_id=0的情形
                if ($val['parent_id'])
                {
                    $this->category_path[] = $val['parent_id'];
                    $this->get_parent_id($val['parent_id']);
                }
                break;
            }
        }
    }
}