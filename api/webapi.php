<?php
class WebAPI
{
    function __construct($action){
        if (method_exists($this, $action)) {
            $this -> $action();
        } else {
            $this -> nonFunction($action);
        }
        exit();
    }
    public function queryPharmaciesByTime()
    {
        $time = @$_POST["time"];
        $Day  = @$_POST["Day"];
        $result = array();
        $manager = new MongoDB\Driver\Manager('mongodb://localhost:27017');
        $this->manager = $manager;
        $filter = [];
        $options = [];
        $query = new MongoDB\Driver\Query($filter, $options);
        $res = $this->manager->executeQuery("test.pharmacies", $query)->toArray();
        for ($i=0; $i < sizeof($res); $i++) { 
            $time_array = $this->cutTime($res[$i]->{"openingHours"});
            for ($j=0; $j <sizeof($time_array) ; $j++) { 
                preg_match('/((0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])) - ((0[0-9]|1[0-9]|2[0-3]):([0-5][0-9]))/',$time_array[$j],$hour);
                $week=preg_split('/((0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])) - ((0[0-9]|1[0-9]|2[0-3]):([0-5][0-9]))/',$time_array[$j]);
                if($time>$hour[1]&&$time<$hour[4]&&$Day==""){
                    array_push($result, $res[$i]);
                    break;
                }
                else if($time>$hour[1]&&$time<$hour[4]&&$Day!=""){
                    if(preg_match("/".$Day."/",$week[0])){
                        array_push($result, $res[$i]);
                        break;
                    }
                }
            }
        }
        echo json_encode($result);
    }
    public function search()
    {
        $cond = array();
        $temp = array();
        $keyWord = @$_POST['keyWord'];
        $manager = new MongoDB\Driver\Manager('mongodb://localhost:27017');
        $this->manager = $manager;
        array_push($temp, array(
            '$or'=>array(
                array('name'=>array('$regex' => new MongoDB\BSON\Regex("$keyWord",'i'))),
                array('masks.name'=>array('$regex' => new MongoDB\BSON\Regex("$keyWord",'i')))
            )
        ));
        $cond['$and'] = $temp;
        $filter = $cond;
        $options = [];
        $query = new MongoDB\Driver\Query($filter, $options);
        $res = $this->manager->executeQuery("test.pharmacies", $query)->toArray();
        if(isset($res))
            $r = $res;
        else
            $r = null;
        echo json_encode($r);
    }
    public function topX()
    {
        $result = array();
        $cond = array();
        $temp = array();
        $top_count = @$_POST['top_count'];
        $start_time = @$_POST['start_time'];
        $end_time = @$_POST['end_time'];
        array_push($temp, array('purchaseHistories.transactionDate'=>array( '$gt' => $start_time)));
        array_push($temp, array('purchaseHistories.transactionDate'=>array( '$lt' => $end_time)));
        $manager = new MongoDB\Driver\Manager('mongodb://localhost:27017');
        $this->manager = $manager;
        $cond['$and'] = $temp;
        $filter = $cond;
        $options = [ 'projection' => ['_id' => 0,'name'=>1,'purchaseHistories.transactionDate'=>1,'purchaseHistories.transactionAmount'=>1]];
        $query = new MongoDB\Driver\Query($filter, $options);
        $res = $this->manager->executeQuery("test.user", $query)->toArray();
        if(isset($res))
            $r = $res;
        else
            $r = null;
        for ($h=0; $h < sizeof($r); $h++) { 
            $sum=0;
            for ($i=0; $i < sizeof($r[$h]->{'purchaseHistories'}); $i++) {
                if($r[$h]->{'purchaseHistories'}[$i]->{'transactionDate'}>$start_time&&$r[$h]->{'purchaseHistories'}[$i]->{'transactionDate'}<$end_time){
                    $sum=$sum+$r[$h]->{'purchaseHistories'}[$i]->{'transactionAmount'};
                }
            }
            array_push($result, array('name'=>$r[$h]->{'name'},'total'=>$sum));
        }
        function cmp($a,$b)
        {
            if($a['total'] == $b['total']) return 0;
            return ($a['total'] > $b['total']) ? 1 : -1;
        }
        uasort($result,"cmp");
        array_slice($result, 0, $top_count);
        echo json_encode($result);
    }
    public function getTotalAmontAndDollar()
    {
        $cond = array();
        $temp = array();
        $start_time = @$_POST['start_time'];
        $end_time = @$_POST['end_time'];
        array_push($temp, array('purchaseHistories.transactionDate'=>array( '$gt' => $start_time)));
        array_push($temp, array('purchaseHistories.transactionDate'=>array( '$lt' => $end_time)));
        $manager = new MongoDB\Driver\Manager('mongodb://localhost:27017');
        $this->manager = $manager;
        $cond['$and'] = $temp;
        $filter = $cond;
        $options = [ 'projection' => ['_id' => 0,'purchaseHistories.transactionDate'=>1,'purchaseHistories.transactionAmount'=>1,'purchaseHistories.maskName'=>1]];
        $query = new MongoDB\Driver\Query($filter, $options);
        $res = $this->manager->executeQuery("test.user", $query)->toArray();
        if(isset($res))
            $r = $res;
        else
            $r = null;
        $sum=0;
        $masks_sum=0;
        for ($h=0; $h < sizeof($r); $h++) { 
            for ($i=0; $i < sizeof($r[$h]->{'purchaseHistories'}); $i++) {
                if($r[$h]->{'purchaseHistories'}[$i]->{'transactionDate'}>$start_time&&$r[$h]->{'purchaseHistories'}[$i]->{'transactionDate'}<$end_time){
                    $sum=$sum+$r[$h]->{'purchaseHistories'}[$i]->{'transactionAmount'};
                    preg_match_all('/\(([0-9]|[0-9][0-9]) per pack\)$/',$r[$h]->{'purchaseHistories'}[$i]->{'maskName'},$str);
                    $masks_sum = $masks_sum + $str[1][0];
                }
            }
        }
        echo json_encode(array('total amount of masks'=>$sum,'dollar value'=>$masks_sum));
    }
    public function getMaskByPharmacies()
    {
        $pharmacyName = @$_POST['pharmacyName'];
        $sort_key = @$_POST['sort_key'];
        $manager = new MongoDB\Driver\Manager('mongodb://localhost:27017');
        $this->manager = $manager;
        $filter = ['name'=>$pharmacyName];
        $query = [
            "aggregate" => "pharmacies",
            "pipeline" => [
                ['$match'=>$filter],
                ['$unwind'=>'$masks'],
                ['$project' =>['_id'=>0]],
                ['$sort'=>['masks.'.$sort_key=>1]],
            ],
            "explain"=>false
        ];
        $command = new MongoDB\Driver\Command($query);
        $result = $this->manager->executeCommand('test', $command)->toArray();
        echo json_encode($result);
    }
    public function getPharmaciesBymask()
    {
        $ceil = @$_POST['ceil'];
        $floor = @$_POST['floor'];
        $less = @$_POST['less'];
        $more = @$_POST['more'];
        $result = array();
        $manager = new MongoDB\Driver\Manager('mongodb://localhost:27017');
        $this->manager = $manager;
        $filter = [];
        $query = [
            "aggregate" => "pharmacies",
            "pipeline" => [
                ['$match'=>(Object)$filter],
                ['$project' =>['_id'=>0]],
                ['$sort'=>['masks.name'=>1]],
            ],
            "explain"=>false
        ];
        $command = new MongoDB\Driver\Command($query);
        $pageData = $this->manager->executeCommand('test', $command)->toArray();
        for ($i=0; $i < sizeof($pageData); $i++) { 
            $amount = 0;
            for ($j=0; $j < sizeof($pageData[$i]->{'masks'}); $j++) {
                if($pageData[$i]->{'masks'}[$j]->{'price'}>$floor&&$pageData[$i]->{'masks'}[$j]->{'price'}<$ceil){
                    $amount++;
                }
            }
            if($amount<$less&&$less!=""&&$amount!=0&&$less!=0){
                array_push($result, $pageData[$i]);
            }
            elseif ($more>$amount&&$more!=""&&$amount!=0&&$more!=0) {
                array_push($result, $pageData[$i]);
            }
        }
        echo json_encode($result);
    }
    public function buyMask($value='')
    {
        $price = 0;
        $pharmacyName = @$_POST['pharmacyName'];
        $maskName = @$_POST['maskName'];
        $userName = @$_POST['userName'];
        $manager = new MongoDB\Driver\Manager('mongodb://localhost:27017');
        $this->manager = $manager;
        $filter = ["name" => $pharmacyName];
        $options = [];
        $query = new MongoDB\Driver\Query($filter, $options);
        $res = $this->manager->executeQuery("test.pharmacies", $query)->toArray();
        for ($i=0; $i < sizeof($res[0]->{'masks'}); $i++) { 
            if($res[0]->{'masks'}[$i]->{'name'}==$maskName){
                $price = $res[0]->{'masks'}[$i]->{'price'};
            }

        }
        $cashBalancePharmacy=$res[0]->{"cashBalance"};
        $cashBalancePharmacy=$cashBalancePharmacy+$price;
        $filter = ["name" => $userName];
        $options = [];
        $query = new MongoDB\Driver\Query($filter, $options);
        $res = $this->manager->executeQuery("test.user", $query)->toArray();
        $cashBalanceUser = $res[0]->{"cashBalance"};
        $cashBalanceUser = $cashBalanceUser-$price;
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->update(
            array("name"=>$userName),
            array('$push' =>array(
                'purchaseHistories' => array(
                    'pharmacyName' => $pharmacyName,
                    'maskName' => $maskName,
                    'transactionAmount' => $price,
                    'transactionDate' => date("Y-m-d h:i:s"),
                    )
                ),
                '$set' =>array(
                    'cashBalance' => $cashBalanceUser
                )
            )
        );
        $this->manager->executeBulkWrite("test.user", $bulk);
        $bulk2 = new MongoDB\Driver\BulkWrite;
        $bulk2->update(
            array("name"=>$pharmacyName),
            array('$set' =>array(
                'cashBalance' => $cashBalancePharmacy
                )
            )
        );
        $this->manager->executeBulkWrite("test.pharmacies", $bulk2);
        echo json_encode(array("cashBalance of ".$pharmacyName => $cashBalancePharmacy,"cashBalance of ".$userName =>$cashBalanceUser));
    }
    public function nonFunction($action) {
        $json = array("err"=>"1" ,"msg"=>"NonExistentAPI call - ".$action );
        echo json_encode($json);
    }
    private function cutTime($value)
    {
        $time_array =explode("/",$value);
        return $time_array;
    }
}