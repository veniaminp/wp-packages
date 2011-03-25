<?php
/**
 * WebProduction Packages
 * Copyright (C) 2007-2010  WebProduction <webproduction.com.ua>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU LESSER GENERAL PUBLIC LICENSE
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * Интерфейс data-процессора
 *
 * @author Max
 * @copyright WebProduction
 * @package PackageLoader
 */
interface PackageLoader_IDataProcessor {

    /**
     * Вызывается в момент поступления данных в PackageLoader
     * (в момент registerCSS[JS]Data())
     *
     * @param string $data
     * @return string
     */
    public function processBefore($data);

    /**
     * Вызывается в момент получения данных из PackageLoader'a
     * (в момент getCSS[JS]Data())
     *
     * @param string $data
     * @return string
     */
    public function processAfter($data);

}