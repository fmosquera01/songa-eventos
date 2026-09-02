<?php

declare(strict_types=1);

class EventoEstado
{
    public static function obtener(PDO $db, int $eventoId): ?array
    {
        if ($eventoId <= 0) return null;
        $stmt = $db->prepare("SELECT id,nombre,descripcion,tipo,fecha_evento,hora_inicio,hora_fin,estado,creado_por,creado_en FROM eventos WHERE id=:id LIMIT 1");
        $stmt->execute([':id'=>$eventoId]);
        $evento=$stmt->fetch(PDO::FETCH_ASSOC);
        return $evento ?: null;
    }

    public static function obtenerActivo(PDO $db): ?array
    {
        $stmt=$db->query("SELECT id,nombre,descripcion,tipo,fecha_evento,hora_inicio,hora_fin,estado,creado_por,creado_en FROM eventos WHERE estado='ACTIVO' ORDER BY id ASC LIMIT 2");
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)!==1) return null;
        return $rows[0];
    }

    public static function cantidadActivos(PDO $db): int
    {
        return (int)$db->query("SELECT COUNT(*) FROM eventos WHERE estado='ACTIVO'")->fetchColumn();
    }

    public static function estaFinalizado(array $evento): bool
    {
        return strtoupper(trim((string)($evento['estado']??'')))==='FINALIZADO';
    }

    public static function estaActivo(array $evento): bool
    {
        return strtoupper(trim((string)($evento['estado']??'')))==='ACTIVO';
    }

    public static function estaEnBorrador(array $evento): bool
    {
        return strtoupper(trim((string)($evento['estado']??'')))==='BORRADOR';
    }

    public static function permiteModificar(array $evento): bool
    {
        return in_array(strtoupper(trim((string)($evento['estado']??''))),['BORRADOR','ACTIVO'],true);
    }

    public static function exigirEvento(PDO $db,int $eventoId): array
    {
        $evento=self::obtener($db,$eventoId);
        if (!$evento) { http_response_code(404); die('Evento no encontrado.'); }
        return $evento;
    }

    public static function exigirModificable(PDO $db,int $eventoId): array
    {
        $evento=self::exigirEvento($db,$eventoId);
        if (!self::permiteModificar($evento)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Evento bloqueado</title><style>body{margin:0;font-family:Arial;background:#f3f4f6;min-height:100vh;display:flex;align-items:center;justify-content:center}.card{max-width:520px;background:#fff;border-radius:16px;padding:35px;text-align:center;box-shadow:0 8px 30px #0002}.icon{font-size:60px}h1{font-size:26px}p{color:#6b7280;line-height:1.6}.estado{display:inline-block;padding:7px 13px;border-radius:20px;background:#dbeafe;color:#1e40af;font-weight:bold}.btn{display:inline-block;margin-top:20px;padding:11px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold}</style></head><body><div class="card"><div class="icon">🔒</div><h1>Evento bloqueado</h1><p>Este evento ya no permite modificaciones.<br>La información solamente puede ser consultada.</p><div class="estado">'.htmlspecialchars((string)$evento['estado']).'</div><br><a class="btn" href="evento.php?id='.(int)$eventoId.'">← Volver al evento</a></div></body></html>';
            exit;
        }
        return $evento;
    }

    /**
     * Cambia el estado de forma segura. GET_LOCK evita que dos peticiones
     * simultáneas puedan activar dos eventos diferentes.
     */
    public static function cambiar(PDO $db,int $eventoId,string $nuevoEstado): void
    {
        $nuevoEstado=strtoupper(trim($nuevoEstado));
        $permitidos=['BORRADOR','ACTIVO','FINALIZADO','CANCELADO'];
        if (!in_array($nuevoEstado,$permitidos,true)) throw new RuntimeException('Estado no válido.');
        if ($nuevoEstado==='ACTIVO') {
            $lock=(int)$db->query("SELECT GET_LOCK('songa_evento_activo',10)")->fetchColumn();
            if ($lock!==1) throw new RuntimeException('No se pudo obtener el bloqueo de activación.');
            try {
                $db->beginTransaction();
                $stmt=$db->query("SELECT id FROM eventos WHERE estado='ACTIVO' LIMIT 1 FOR UPDATE");
                $activo=$stmt->fetchColumn();
                if ($activo!==false && (int)$activo!==$eventoId) throw new RuntimeException('Ya existe otro evento ACTIVO. Finalícelo o cámbielo antes de activar este evento.');
                $stmt=$db->prepare("UPDATE eventos SET estado=:estado WHERE id=:id");
                $stmt->execute([':estado'=>'ACTIVO',':id'=>$eventoId]);
                $db->commit();
            } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
            finally { $db->query("SELECT RELEASE_LOCK('songa_evento_activo')"); }
            return;
        }
        $stmt=$db->prepare("UPDATE eventos SET estado=:estado WHERE id=:id");
        $stmt->execute([':estado'=>$nuevoEstado,':id'=>$eventoId]);
    }

    public static function avisoSoloLectura(array $evento): string
    {
        if (!self::estaFinalizado($evento)) return '';
        return '<div style="margin:0 0 20px;padding:14px 18px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;font-family:Arial,sans-serif"><strong>🔒 Evento finalizado</strong><br><span style="font-size:14px">Este evento está en modo consulta. No se pueden realizar modificaciones.</span></div>';
    }
}
