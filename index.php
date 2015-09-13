<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>日本語３０００</title>
    <!-- 
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
    -->
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

      set_time_limit(0);


      if ( $query_common_word = $mysqli->query("SELECT word FROM common") ) {
        for ( $x = 0; $x < 100; $x++ ) {
          $common_word = $query_common_word->fetch_assoc();
        // while ( $common_word = $query_common_word->fetch_assoc() ) {
          // echo "common_word: <pre>" . print_r($common_word, 1) . "</pre>";
          $word = $mysqli->real_escape_string($common_word['word']);

          // Grab all instances of this word from dictionary DB, using kanji
          if ($query_dictionary_kanji = $mysqli->query("SELECT $columns FROM dictionary WHERE kanji='$word'")) {
            while ($dictionary_kanji = $query_dictionary_kanji->fetch_assoc()) {
              // echo "dictionary_kanji: <pre>" . print_r($dictionary_kanji, 1) . "</pre>";
              $kanji = $word;
              $kana = $dictionary_kanji['kana'];
              $def01 = $dictionary_kanji['def01'];

              echo "<p>new kanji: " . $dictionary_kanji['kanji'] . " -- " . $dictionary_kanji['kana'] . " -- " . $dictionary_kanji['def01'] . "</p>";
              echo "<p>kanji: $kanji</p><pre>" . print_r($dictionary_kanji, 1) . "</pre>";

              // Check for any words already in newdict using the same kanji
              if ( $query_newdict_kanji = $mysqli->query("SELECT * FROM newdict WHERE kanji='$kanji'")) {
                if ( $query_newdict_kanji->num_rows != 0 ) {
                  echo "<p>********** matches: " . $query_newdict_kanji->num_rows . " ***********</p>";
                  while ( $newdict_kanji = $query_newdict_kanji->fetch_assoc() ) {
                    $is_new = 0;
                    echo "<p>comparing to: " . $newdict_kanji['kanji'] . " -- " . $newdict_kanji['kana'] . " -- " . $newdict_kanji['def01'] . "</p>";
                    echo "<p>kanji: " . $newdict_kanji['kanji'] . "</p><pre>" . print_r($newdict_kanji, 1) . "</pre>";
                    $diffarray = array_diff($dictionary_kanji, $newdict_kanji);
                    echo "<p>------------------- RESULT ------------------</p>";
                    echo "<p>kanji: $kanji</p><pre>" . print_r($diffarray, 1) . "</pre>";
                    echo "<p>----------------- END RESULT ----------------</p>";
                    if ( !empty( $diffarray ) ) {
                      echo "<p>-------------------- THEY ARE DIFFERENT -------------------------</p>";
                      /*
                      echo "<h3>$kanji</h3>";
                      echo "<pre>" . print_r($dictionary_kanji, 1) . "</pre>";
                      echo "<p>------------------- versus ------------------</p>";
                      echo "<pre>" . print_r($newdict_kanji, 1) . "</pre>";
                      echo "<p>------------------- result ------------------</p>";
                      echo "<p>x: $x | kanji: $kanji</p><pre>" . print_r($diffarray, 1) . "</pre>";
                      echo "<p>---------------------------------------------</p>";
                      echo "<p>---------------------------------------------</p>";
                      echo "<p>---------------------------------------------</p>";
                      */
                      // This isn't a duplicate (of which there seem to be many... ?)
                      $newdict_key = $newdict_kanji['key'];

                      // echo "existing_kanji: <pre>" . print_r($existing_kanji, 1) . "</pre>";
                      if ( $newdict_kanji['def01'] != $def01 ) {
                        echo "<p>-------------------- SAME KANJI, DIFFERENT DEFINITION-------------------------</p>";
                        // This is a new word using the same kanji as another and we should add this to newdict
                        $is_new = 1;
                      } else {
                        if ( $newdict_kanji['kana'] != ($kana . "; ") ) {
                          echo "<p>----------------- IT'S NOT ENTIRELY THE SAME -----------------------</p>";
                          if ( 0 ===  preg_match("/^$kana\; /", $newdict_kanji['kana']) && 0 ===  preg_match("/ $kana\; /", $newdict_kanji['kana']) ) {
                            echo "<p>----------------- AND IT DOESN'T SHOW UP IN A LIST -----------------------</p>";
                            echo "<p>-------------------- SAME KANJI, SAME DEFINITION-------------------------</p>";
                            // This is not a new word, but it's a new kana for this kanji / def pair
                            $existing_kana = "";
                            // If there is, in fact, already a kana...
                            // (the dictionary db stores the character in the kanji column if there is no kanji...)
                            if ( !empty($newdict_kanji['kana'] ) ) {
                              $existing_kana = $newdict_kanji['kana'];
                            }
                            $kana = $mysqli->real_escape_string($kana);
                            $new_kana = $existing_kana . $kana . "; ";

                            $def01 = $mysqli->real_escape_string($def01);

                            echo "<p>----------------- UPDATING WITH KANA -----------------------</p>";
                            if ( !$mysqli->query("UPDATE newdict SET kana = '$new_kana' WHERE `key` = $newdict_key") ) {
                              printf("Error updating: %s\n", $mysqli->error);
                              exit();
                            }
                          }
                        }
                        echo "<p>-------------------- BUT NOT DIFFERENT ENOUGH (KANA ALREADY PRESENT) -------------------------</p>";
                      }
                    } else {
                      echo "<p>-------------------- THEY ARE THE SAME -------------------------</p>";
                    }
                  }
                } else {
                  echo "<p>-------------------- COMPLETELY NEW -------------------------</p>";
                  // There is no existing entry in newdict and we should add this
                  // echo "New word, new kanji";
                  $is_new = 1;
                }
              }

              if ( $is_new ) {
                // echo "adding kanji: <pre>" . print_r($dictionary_kanji, 1) . "</pre>";
                // if it is new, let us add it okay?
                $values = "";
                // serialize those kana no matter what
                if ( ! empty($dictionary_kanji['kana']) ) {
                  $dictionary_kanji['kana'] = $mysqli->real_escape_string($dictionary_kanji['kana']) . "; ";
                }
                foreach ( $dictionary_kanji as $key => $value ) {
                  if ( $key != 'kana' ) {
                    $value = $mysqli->real_escape_string($value);
                  }
                  $values .= "'$value', ";
                }
                $values = rtrim($values, ", ");

                echo "<p>----------------- ADDING NEW KANJI -----------------------</p>";

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
