<?php
// conexion/connection.php
// Requisitos: extension=pdo_pgsql habilitada en php.ini

class DB {
  private static ?PDO $cn = null;

  public static function conn(): PDO {
    if (self::$cn === null) {
      //valores de la BD
      $host = 'localhost';
      $port = 5432;
      $dbname = 'checador';
      $user = 'postgres';  
      $pass = 'CTA1610';

      $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

      self::$cn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);

      // Asegurar UTF-8 en cliente (opcional)
      self::$cn->exec("SET client_encoding TO 'UTF8'");
    }
    return self::$cn;
  }
}
