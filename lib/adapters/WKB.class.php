<?php
/*
 * (c) Camptocamp <info@camptocamp.com>
 * (c) Patrick Hayes
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PHP Geometry/WKT encoder/decoder
 *
 * Mainly inspired/adapted from OpenLayers( http://www.openlayers.org ) 
 *   Openlayers/format/WKT.js
 *
 * @package    sfMapFishPlugin
 * @subpackage GeoJSON
 * @author     Camptocamp <info@camptocamp.com>
 */
class WKB extends GeoAdapter
{

  /**
   * Read WKB into geometry objects
   *
   * @param string $wkb Well-known-binary string
   * @param bool $is_hex_string If this is a hexedecimal string that is in need of packing
   * @return Geometry|GeometryCollection
   */
  public function read($wkb, $is_hex_string = FALSE) {
		if ($is_hex_string) {
			$wkb = pack('H*',$wkb);
		}
		
		$mem = fopen('php://memory', 'r+');
	  fwrite($mem, $wkb);
	  fseek($mem, 0);
	
		$geometry = $this->getGeometry($mem);
		fclose($mem);
		return $geometry;
  }

  /**
   * Serialize geometries into WKB string.
   *
   * @param Geometry $geometry
   *
   * @return string The WKT string representation of the input geometries
   */
  public function write(Geometry $geometry, $write_as_hex = FALSE) {
    //@@TODO
  }

	function getGeometry(&$mem) {
		$base_info = unpack("corder/Ltype", fread($mem, 5));
		if ($base_info['order'] !== 1) {
			throw new Exception('Only NDR (little endian) SKB format is supported at the moment');
		}
	 
		switch ($base_info['type']) {
		  case 1:
		    return $this->getPoint($mem);
		  case 2:
		    return $this->getLinstring($mem);
		  case 3:
		    return $this->getPolygon($mem);
		  case 4:
		    return $this->getMulti($mem,'point');
		  case 5:
		    return $this->getMulti($mem,'line');
		  case 6:
		    return $this->getMulti($mem,'polygon');
		  case 7:
		    return $this->getMulti($mem,'geometry');
		}
	}
	
	function getPoint(&$mem) {
		$point_coords = unpack("d*", fread($mem,16));
		return new Point($point_coords[1],$point_coords[2]);
	}
	
	function getLinstring(&$mem) {
	  // Get the number of points expected in this string out of the first 4 bytes
		$line_length = unpack('L',fread($mem,4));
			
		// Read the nubmer of points x2 (each point is two coords) into decimal-floats
		$line_coords = unpack('d'.$line_length[1]*2, fread($mem,$line_length[1]*16));
			
		// We have our coords, build up the linestring
		$components = array();
		$i = 1;
		$num_coords = count($line_coords);
		while ($i <= $num_coords) {
			$components[] = new Point($line_coords[$i],$line_coords[$i+1]);
		  $i += 2;
		}
		return new LineString($components);
	}
	
	function getPolygon(&$mem) {
	  // Get the number of linestring expected in this poly out of the first 4 bytes
		$poly_length = unpack('L',fread($mem,4));
		
		$components = array();
		$i = 1;
		while ($i <= $poly_length[1]) {
			$components[] = $this->getLinstring($mem);
		  $i++;
		}
		return new Polygon($components);
	}
	
	function getMulti(&$mem, $type) {
	  // Get the number of items expected in this multi out of the first 4 bytes
		$multi_length = unpack('L',fread($mem,4));
		
		$components = array();
		$i = 1;
		while ($i <= $multi_length[1]) {
			$components[] = $this->getGeometry($mem);
		  $i++;
	  }
	  switch ($type) {
		  case 'point':
		    return new MultiPoint($components);
		  case 'line':
		    return new MultiLineString($components);
		  case 'polygon':
		    return new MultiPolygon($components);
		  case 'geometry':
		    return new GeometryCollection($components);
		}
	}

}
