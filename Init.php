<?php
/**
 * This file is part of Modelo349 plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\Modelo349;

use FacturaScripts\Core\Controller\ApiRoot;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Template\InitClass;

/**
 * Inicialización del plugin Modelo349: registra el endpoint de solo lectura de la API.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Init extends InitClass
{
    /**
     * Registra la ruta del endpoint /api/3/modelo349 y lo añade al listado de recursos de la API.
     */
    public function init(): void
    {
        Kernel::addRoute('/api/3/modelo349', 'ApiModelo349', -1);
        ApiRoot::addCustomResource('modelo349');
    }

    /**
     * Acciones al desinstalar el plugin: ninguna.
     */
    public function uninstall(): void
    {
    }

    /**
     * Acciones al instalar o actualizar el plugin: ninguna.
     */
    public function update(): void
    {
    }
}
