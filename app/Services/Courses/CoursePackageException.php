<?php

namespace App\Services\Courses;

use RuntimeException;

/**
 * Error de negocio con un paquete de curso. Su mensaje es seguro para mostrar al usuario
 * (no contiene rutas internas ni detalles técnicos).
 */
class CoursePackageException extends RuntimeException {}
