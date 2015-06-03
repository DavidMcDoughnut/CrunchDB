<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
  <head>
    <title>AandV</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
     <link rel="stylesheet" href="logo test.css"></link>
     <link href='http://fonts.googleapis.com/css?family=Josefin+Sans:100' rel='stylesheet' type='text/css'>
  
     <script> 
      function getOutput()
      {
        var companyVariable = document.getElementById('company').value;
        var userKey = '?user_key=89b01410b2fcdc75064982524339c61c';
        var request = new XMLHttpRequest();
        var response;
        var output = document.getElementById("output");
        
        request.open('GET', 'http://api.crunchbase.com/v/2/organization/' + companyVariable + userKey, true); 
        request.send(null);
        
        if(request.status == 200)
        {
          response = JSON.parse(request.responseText);
          var uuidVariable = response.data.type;
          output.innerHTML = "<h1>" + uuidVariable + "</h1>";
        }
        
      }
      
      function clearOutput()
      {
        output.innerHTML = "";
      }
      </script>
  </head>
  <body>


<div id="topbanner"></div>

<div id="seperator"></div>
<div id="container">
<div id="home">Pulling IoT comps from crunchbase and inserting into crunchiot MySQL db</div>


  <?php
/*

Notes for dmcd:

1.Script to connect to mySQL DB "crunchiot" and select table "iotcompanies" 
2.then sets up row of field names
3. uses cURL to pull JSON data from iot category of Crunchbase API: category_uuids=ed3a589dc9a73cbb9feb245f011e1d54
4. holds data in an array which is looped through with SQL which INSERTS into db table: `crunchiot`.`iotCompanies`
5. SQL order is echoed and success is printed (ideally)
*/

{   //Connect and Test MySQL and specific DB (return $dbSuccess = T/F)
        
      $hostname = "crunchiot.db.10718538.hostedresource.com";
      $username = "crunchiot";
      $password = "Crunchiot!1";      
      $databaseName = "crunchiot";


      $dbConnected = @mysql_connect($hostname, $username, $password);
      $dbSelected = @mysql_select_db($databaseName,$dbConnected);

      $dbSuccess = true;
      if ($dbConnected) {
        if (!$dbSelected) {
          echo "DB connection FAILED<br /><br />";
          $dbSuccess = false;
        }   
      } else {
        echo "MySQL connection FAILED<br /><br />";
        $dbSuccess = false;
      }
}  

  //Execute code ONLY if connections were successful  
if ($dbSuccess) {
  
  { //Setup ARRAY of field names 
    $iotCoField = array(
          '`updated`' => '`updated`',
          '`created`' => '`created`',
          '`path`' => '`path`',
          '`name`' => '`name`',
          '`type`' => '`type`',
          '`apiurl`' => '`apiurl`',

    );
}

{ //Use cURL to pull iot category companies from Crunchbase API & setup ARRAY of data ROWS
    //Initiaize cUrl
    $ch = curl_init();
    //set the url (CRUNCHBASE API SWITCHED TO HTTPS 1/11/15)
    $url = 'httpS://api.crunchbase.com/v/2/organizations?category_uuids=ed3a589dc9a73cbb9feb245f011e1d54&user_key=89b01410b2fcdc75064982524339c61c&order=created_at%20desc';    
    //Set options   
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);    
  //Execute 
    $jsonContent = curl_exec($ch); 
    //Close curl session / free resources
    curl_close($ch);
    //Decode the json string into an array
    $json = json_decode($jsonContent, true);     
    //Loop through the results
    for($i=0; $i<$json['data']['paging']['total_items']; $i++){
       $iotCoData[$i] = array(
             $json['data']['items'][$i]['updated_at'],
             $json['data']['items'][$i]['created_at'],
             $json['data']['items'][$i]['path'],
             $json['data']['items'][$i]['name'],
             $json['data']['items'][$i]['type']);
}

}
       $numRows =  sizeof($iotCoData);
    


{ //SQL statement with ARRAYS -> fieldnames part of INSERT statement 
    $iotSQLinsert = 'INSERT INTO `crunchiot`.`iotCompaniesByName` (
                  '.$iotCoField['`updated`'].',
                  '.$iotCoField['`created`'].',
                  '.$iotCoField['`path`'].',
                  '.$iotCoField['`name`'].',
                  '.$iotCoField['`type`'].',
                  '.$iotCoField['`apiurl`'].'           
                  )';
                
  //VALUES  part of INSERT statement                  
    $iotSQLinsert .=  "VALUES ";      
    
    $i = 0;   
    while($i < $numRows) {      
      $iotSQLinsert .=  "(
                    '".$iotCoData[$i][0]."',
                    '".$iotCoData[$i][1]."',
                    '".$iotCoData[$i][2]."',
                    '".$iotCoData[$i][3]."',
                    '".$iotCoData[$i][4]."',
                    '"."https://api.crunchbase.com/v/2/".$iotCoData[$i][2]."?user_key=89b01410b2fcdc75064982524339c61c"."'
                    )"; 

      if ($i < ($numRows - 1)) {
        $iotSQLinsert .=  ",";
      } 
      $i++;
    }
}
   // $iotSQLinsert .= 'HAVING `name` != "Expert Consulting"';
  //ON DUPLICATE KEY (if company record exists, update instead of insert)
   $iotSQLinsert .=  'ON DUPLICATE KEY UPDATE

                  '.$iotCoField['`updated`']."="." VALUES(".$iotCoField['`updated`'].')'.',
                  '.$iotCoField['`created`']."="." VALUES(".$iotCoField['`created`'].')'.',
                  '.$iotCoField['`path`']."="." VALUES(".$iotCoField['`path`'].')'.',
                  '.$iotCoField['`name`']."="." VALUES(".$iotCoField['`name`'].')'.',
                  '.$iotCoField['`type`']."="." VALUES(".$iotCoField['`type`'].')'.',
                  '.$iotCoField['`apiurl`']."="." VALUES(".$iotCoField['`apiurl`'].')';
 //                  'WHERE'.$iotCoField['`name`']."!="."`Expert & Consulting`  ";

//custom code to rename Expert & Consulting because "&" character breaks the xml output and makes it invalid (need to fix this somehow)
//$iotSQLinsert .= 'IF `name` != "Expert Consulting"';



//'.$iotCoField['`name`']."!=".'"Expert & Consulting"';

// $sql = 'SELECT * FROM `iotCompaniesByName` HAVING `name` != "Expert Consulting"';


// '.$iotCoField['`name`']."!="." VALUES(Expert & Consulting)";

// '.$iotCoField['`name`']."<>"."'Expert & Consulting'";



  //`crunchiot`.`iotCompaniesByName` SET `name` = \'Expert Consulting\' WHERE CONVERT(`iotCompaniesByName`.`name` USING utf8) = \'Expert & Consulting\' LIMIT 1;';
 



//custom code to rename Expert & Consulting because "&" character breaks the xml output and makes it invalid (need to fix this somehow)
//  $iotSQLinsert = 'UPDATE `crunchiot`.`iotCompaniesByName` SET `name` = \'Expert Consulting\' WHERE CONVERT(`iotCompaniesByName`.`name` USING utf8) = \'Expert & Consulting\' LIMIT 1;';
 

{ //Echo and Execute the SQL and test for success   
    echo "<strong><u>SQL:<br /></u></strong>";
    echo $iotSQLinsert."<br /><br />";
      
    if (mysql_query($iotSQLinsert))  {        
      echo "was SUCCESSFUL.<br /><br />";
    } else {
      echo "FAILED.<br /><br />";   
    }

  }
}

      
 //END ($dbSuccess)

?>


</div>

  <!-- <h1>XmlHttpRequest From Crunchbase</h1>
      <div>
        <label>company (name):
        </label>
        <input type="text" id="company" />
        </div>
        <div>
        <button onclick="getOutput();" class="submit-button">Get Info</button>
        <button onclick="clearOutput();">Clear</button>
      </div>
      <div id="output"></div></div>
  -->  
</body>
