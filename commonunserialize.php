<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>日本語３０００ - unserialize the kana in the list of common words with definitions</title>
  </head>
  <body>
    <?php

      $dict = array();
      $common = array();

      $columns = 'kanji, kana, def01, def02, def03, def04, def05, def06, def07, def08, def09, '; 
      for ($x = 10; $x < 53; $x++) {
        $columns .= 'def' . $x;
        if ($x != 52) {
          $columns .= ', ';
        }
      }

      $mysqli = new mysqli("localhost", "jmdict", "jmdict", "jmdict");

      /* check connection */
      if ($mysqli->connect_errno) {
        printf("Connect failed: %s\n", $mysqli->connect_error);
        exit();
      }

      /* change character set to utf8 */
      if (!$mysqli->set_charset("utf8")) {
        printf("Error loading character set utf8: %s\n", $mysqli->error);
        exit();
      }

      $mysqli->select_db('jmdict');

      $new_dict = array();

      //set_time_limit(0);

      if ( $query_common_word = $mysqli->query("SELECT * FROM newdict") ) {
        while ( $common_word = $query_common_word->fetch_assoc() ) {
          // unserialize the kana for the db to be exported to csv
          $kana_unserialized = unserialize(base64_decode($common_word['kana']));
          echo "<p>kanji: " . $common_word['kanji'] . "</p>";
          echo "<p>kana: " . print_r($kana_unserialized,1) . "</p>";
          $kana_string = "";

          if ( ! empty($kana_unserialized) ) {
            if ( is_array($kana_unserialized) ) {
              // construct string of unserialized kana for SQL
              foreach ( $kana_unserialized as $kana ) {
                if ( ! empty($kana) ) {
                  $kana_string .= $kana . ", ";
                }
              }
              if ( ! empty($kana_string) ) {
                $kana_string = rtrim($kana_string, ", ");
              }
            } else {
              // this isn't an array of kana, it's just one
              $kana_string = $kana_unserialized;
            }

            echo "<p>kana string: " . $kana_string . "</p>";
          }

          $common_unserialized = $common_word;
          $common_unserialized['kana'] = $kana_string;

          $common_unserialized = array_slice($common_unserialized, 1);

          // echo "<pre>" . print_r($common_unserialized, 1) . "</pre>";

          // construct string of values for SQL
          $values = "";
          foreach ( $common_unserialized as $key => $value ) {
            $value = $mysqli->real_escape_string($value);
            $values .= "'$value', ";
          }
          $values = rtrim($values, ", ");

          /*
          // insert unserialized version into other database
          if ( !$mysqli->query("INSERT INTO commonunserialized($columns) VALUES($values)") ) {
            printf("Error inserting: %s\n", $mysqli->error);
            exit();
          }
          */
        }
        $query_common_word->free();
      }
      $mysqli->close();
    ?>
  </body>
</html>
