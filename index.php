<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>日本語３０００</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
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

      $max_kana_kanji = '';
      $max_kana_num = 0;
      $new_dict = array();

      //set_time_limit(0);


      if ( $query_common_word = $mysqli->query("SELECT word FROM common") ) {
        while ( $common_word = $query_common_word->fetch_assoc() ) {
          // echo "common_word: <pre>" . print_r($common_word, 1) . "</pre>";
          $word = $mysqli->real_escape_string($common_word['word']);

          // Grab all instances of this word from dictionary DB, using kanji
          if ($query_dictionary_kanji = $mysqli->query("SELECT $columns FROM dictionary WHERE kanji='$word'")) {
            while ($dictionary_kanji = $query_dictionary_kanji->fetch_assoc()) {
              // echo "dictionary_kanji: <pre>" . print_r($dictionary_kanji, 1) . "</pre>";
              $kanji = $word;
              $kana = $dictionary_kanji['kana'];
              $def01 = $dictionary_kanji['def01'];
              $is_new = 0;

              // Check for any words already in newdict using the same kanji
              if ( $query_newdict_kanji = $mysqli->query("SELECT * FROM newdict WHERE kanji='$kanji'")) {
                if ( $query_newdict_kanji->num_rows != 0 ) {
                  while ( $existing_kanji = $query_newdict_kanji->fetch_assoc() ) {
                    // echo "existing_kanji: <pre>" . print_r($existing_kanji, 1) . "</pre>";
                    if ( $existing_kanji['def01'] != $def01 ) {
                      // echo "new word, same kanji";
                      // This is a new word using the same kanji as another and we should add this to newdict
                      $is_new = 1;
                    } else {
                      // This is a new kana for an existing word (kanji/def pair)
                      $existing_kana = unserialize($existing_kanji['kana']);
                      $kana = $mysqli->real_escape_string($kana);
                      if ( !is_array($existing_kana) ) {
                        $new_kana = array($existing_kana, $kana);
                      } else {
                        $new_kana = $existing_kana;
                        $new_kana[] = $kana;
                      }

                      $kana_serial = serialize($new_kana);
                      $def01 = $mysqli->real_escape_string($def01);

                      if ( !$mysqli->query("UPDATE newdict SET kana = '$kana_serial' WHERE kanji = '$kanji' AND def01 = '$def01'") ) {
                        printf("Error updating: %s\n", $mysqli->error);
                        exit();
                      }
                    }
                  }
                } else {
                  // There is no existing entry in newdict and we should add this
                  // echo "New word, new kanji";
                  $is_new = 1;
                }
              }

              if ( $is_new ) {
                // echo "adding kanji: <pre>" . print_r($dictionary_kanji, 1) . "</pre>";
                // if it is new, let us add it okay?
                $values = "";
                foreach ( $dictionary_kanji as $key => $value ) {
                  $value = $mysqli->real_escape_string($value);
                  $values .= "'$value', ";
                }
                $values = rtrim($values, ", ");

                if ( !$mysqli->query("INSERT INTO newdict ($columns) VALUES($values)") ) {
                  printf("Error inserting: %s\n", $mysqli->error);
                  exit();
                }
              }
              $query_newdict_kanji->free();
            }
            $query_dictionary_kanji->free();
          }
        }
        $query_common_word->free();
      }
      $mysqli->close();
    ?>
  </body>
</html>
