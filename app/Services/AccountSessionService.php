<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class AccountSessionService
{
    public static function rememberCurrent(): void
    {
        if (!empty($_SESSION['mis_user_id'])) {
            $_SESSION['accounts'][(int)$_SESSION['mis_user_id']]=[
                'version'=>(int)$_SESSION['version'],
                'last_active'=>(int)$_SESSION['last_active'],
            ];
        }
    }

    public static function available(PDO $db, int $timeout): array
    {
        self::rememberCurrent();
        $accounts=[];
        foreach ($_SESSION['accounts']??[] as $id=>$slot) {
            $s=$db->prepare('SELECT id,username,full_name,session_version,must_change_password FROM users WHERE id=? AND status=\'active\' AND legacy_identity=0');
            $s->execute([$id]);$user=$s->fetch();
            if (!$user || (int)$user['session_version']!==(int)$slot['version'] || time()-(int)$slot['last_active']>$timeout) {
                unset($_SESSION['accounts'][$id]);continue;
            }
            $accounts[(int)$id]=$user;
        }
        return $accounts;
    }

    // Called only after password verification or validation of an authenticated slot.
    public static function activate(array $user): void
    {
        self::rememberCurrent();
        $accounts=$_SESSION['accounts']??[];
        $accounts[(int)$user['id']]=['version'=>(int)$user['session_version'],'last_active'=>time()];
        if(count($accounts)>5)throw new RuntimeException('Sign out before adding more than five accounts.');
        session_regenerate_id(true);
        $_SESSION=['mis_user_id'=>(int)$user['id'],'version'=>(int)$user['session_version'],'last_active'=>time(),'accounts'=>$accounts];
    }

    public static function switchTo(PDO $db, int $id, int $timeout): void
    {
        $accounts=self::available($db,$timeout);
        if (!isset($accounts[$id]))throw new RuntimeException('Sign in to this account before switching. Its session may have expired.');
        self::activate($accounts[$id]);
    }
}
