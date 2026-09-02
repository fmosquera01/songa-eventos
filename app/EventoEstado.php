<?php

declare(strict_types=1);

/**
 * Control centralizado del estado de los eventos.
 *
 * Estados utilizados:
 *
 * BORRADOR
 * ACTIVO
 * FINALIZADO
 * CANCELADO
 */

class EventoEstado
{
    /**
     * Obtiene un evento por ID.
     */
    public static function obtener(PDO $db, int $eventoId): ?array
    {
        if ($eventoId <= 0) {
            return null;
        }

        $stmt = $db->prepare("
            SELECT
                id,
                nombre,
                descripcion,
                tipo,
                fecha_evento,
                hora_inicio,
                hora_fin,
                estado,
                creado_por,
                creado_en
            FROM eventos
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $eventoId
        ]);

        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        return $evento ?: null;
    }


    /**
     * Indica si el evento está finalizado.
     */
    public static function estaFinalizado(array $evento): bool
    {
        return strtoupper(
            trim((string)($evento['estado'] ?? ''))
        ) === 'FINALIZADO';
    }


    /**
     * Indica si el evento está activo.
     */
    public static function estaActivo(array $evento): bool
    {
        return strtoupper(
            trim((string)($evento['estado'] ?? ''))
        ) === 'ACTIVO';
    }


    /**
     * Indica si el evento está en borrador.
     */
    public static function estaEnBorrador(array $evento): bool
    {
        return strtoupper(
            trim((string)($evento['estado'] ?? ''))
        ) === 'BORRADOR';
    }


    /**
     * Indica si el evento permite modificaciones.
     *
     * FINALIZADO y CANCELADO no permiten modificaciones.
     */
    public static function permiteModificar(array $evento): bool
    {
        $estado = strtoupper(
            trim((string)($evento['estado'] ?? ''))
        );

        return in_array(
            $estado,
            [
                'BORRADOR',
                'ACTIVO'
            ],
            true
        );
    }


    /**
     * Detiene la ejecución si el evento no existe.
     */
    public static function exigirEvento(
        PDO $db,
        int $eventoId
    ): array {

        $evento = self::obtener(
            $db,
            $eventoId
        );

        if (!$evento) {
            http_response_code(404);
            die('Evento no encontrado.');
        }

        return $evento;
    }


    /**
     * Detiene la ejecución si el evento está finalizado.
     *
     * Se utiliza en páginas que modifican información.
     */
    public static function exigirModificable(
        PDO $db,
        int $eventoId
    ): array {

        $evento = self::exigirEvento(
            $db,
            $eventoId
        );

        if (!self::permiteModificar($evento)) {

            http_response_code(403);

            echo '<!DOCTYPE html>';
            echo '<html lang="es">';
            echo '<head>';
            echo '<meta charset="UTF-8">';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<title>Evento bloqueado</title>';

            echo '<style>';

            echo '
                body {
                    margin: 0;
                    font-family: Arial, sans-serif;
                    background: #f3f4f6;
                    color: #1f2937;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .card {
                    width: min(520px, 100%);
                    background: white;
                    border-radius: 16px;
                    padding: 35px;
                    text-align: center;
                    box-shadow: 0 8px 30px rgba(0,0,0,.12);
                }

                .icon {
                    font-size: 60px;
                    margin-bottom: 10px;
                }

                h1 {
                    margin: 10px 0;
                    font-size: 26px;
                }

                p {
                    color: #6b7280;
                    line-height: 1.6;
                }

                .estado {
                    display: inline-block;
                    margin-top: 8px;
                    padding: 7px 13px;
                    border-radius: 20px;
                    background: #dbeafe;
                    color: #1e40af;
                    font-weight: bold;
                    font-size: 13px;
                }

                .btn {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 11px 20px;
                    background: #2563eb;
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: bold;
                }
            ';

            echo '</style>';
            echo '</head>';

            echo '<body>';

            echo '<div class="card">';

            echo '<div class="icon">🔒</div>';

            echo '<h1>Evento bloqueado</h1>';

            echo '<p>';
            echo 'Este evento ya no permite modificaciones.';
            echo '<br>';
            echo 'La información solamente puede ser consultada.';
            echo '</p>';

            echo '<div class="estado">';
            echo htmlspecialchars(
                (string)$evento['estado']
            );
            echo '</div>';

            echo '<br>';

            echo '<a class="btn" href="evento.php?id=';
            echo (int)$eventoId;
            echo '">';
            echo '← Volver al evento';
            echo '</a>';

            echo '</div>';

            echo '</body>';
            echo '</html>';

            exit;
        }

        return $evento;
    }


    /**
     * Genera un aviso visual para páginas de consulta.
     */
    public static function avisoSoloLectura(
        array $evento
    ): string {

        if (!self::estaFinalizado($evento)) {
            return '';
        }

        return '
            <div style="
                margin:0 0 20px 0;
                padding:14px 18px;
                border-radius:10px;
                background:#eff6ff;
                border:1px solid #bfdbfe;
                color:#1e40af;
                font-family:Arial,sans-serif;
            ">
                <strong>🔒 Evento finalizado</strong><br>
                <span style="font-size:14px;">
                    Este evento está en modo consulta.
                    No se pueden realizar modificaciones.
                </span>
            </div>
        ';
    }
}