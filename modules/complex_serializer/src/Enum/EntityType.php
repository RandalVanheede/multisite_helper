<?php

namespace Drupal\multisite_helper_complex_serializer\Enum;

enum EntityType: string {

  /**
   * Start with underscores as to not collide with actual entity types.
   */
  case GENERIC = '_generic';
  case FIELDABLE = '_fieldable';
  case CONFIG  = '_config';

}
