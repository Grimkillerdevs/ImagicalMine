<?php

namespace pocketmine\entity\utils;

use pocketmine\event\Timings;

class Movement{

    public function __construct(){
    }

    /**
     * move
     * @param Entity $entity - as reference, so we are still on the origin entity object
     * @param float|int $dx
     * @param float|int $dy
     * @param float|int $dz
     *
     * @return bool
     */
    public static function move(&$entity, $dx, $dy, $dz){
        if($dx == 0 and $dz == 0 and $dy == 0){
            return true;
        }
        if($entity->keepMovement){
            $entity->boundingBox->offset($dx, $dy, $dz);
            $entity->setPosition($entity->temporalVector->setComponents(($entity->boundingBox->minX + $entity->boundingBox->maxX) / 2, $entity->boundingBox->minY, ($entity->boundingBox->minZ + $entity->boundingBox->maxZ) / 2));
            $entity->onGround = $entity->isPlayer ? true : false;
            return true;
        }else{
            Timings::$entityMoveTimer->startTiming();
            $entity->ySize *= 0.4;
            $movX = $dx;
            $movY = $dy;
            $movZ = $dz;
            $axisalignedbb = clone $entity->boundingBox;
            $list = $entity->level->getCollisionCubes($this, $entity->level->getTickRate() > 1 ? $entity->boundingBox->getOffsetBoundingBox($dx, $dy, $dz) : $entity->boundingBox->addCoord($dx, $dy, $dz), false);
            foreach($list as $bb){
                $dy = $bb->calculateYOffset($entity->boundingBox, $dy);
                $dx = $bb->calculateXOffset($entity->boundingBox, $dx);
                $dz = $bb->calculateZOffset($entity->boundingBox, $dz);
            }
            $entity->boundingBox->offset(0, $dy, 0);
            $entity->boundingBox->offset($dx, 0, 0);
            $entity->boundingBox->offset(0, 0, $dz);
            $fallingFlag = ($entity->onGround or ($dy != $movY and $movY < 0));

            if($entity->stepHeight > 0 and $fallingFlag and $entity->ySize < 0.05 and ($movX != $dx or $movZ != $dz)){
                $cx = $dx;
                $cy = $dy;
                $cz = $dz;
                $dx = $movX;
                $dy = $entity->stepHeight;
                $dz = $movZ;
                $axisalignedbb1 = clone $entity->boundingBox;
                $entity->boundingBox->setBB($axisalignedbb);
                $list = $entity->level->getCollisionCubes($this, $entity->boundingBox->addCoord($dx, $dy, $dz), false);
                foreach($list as $bb){
                    $dy = $bb->calculateYOffset($entity->boundingBox, $dy);
                    $dx = $bb->calculateXOffset($entity->boundingBox, $dx);
                    $dz = $bb->calculateZOffset($entity->boundingBox, $dz);
                }
                $entity->boundingBox->offset(0, $dy, 0);
                $entity->boundingBox->offset($dx, 0, 0);
                $entity->boundingBox->offset(0, 0, $dz);
                if(($cx ** 2 + $cz ** 2) >= ($dx ** 2 + $dz ** 2)){
                    $dx = $cx;
                    $dy = $cy;
                    $dz = $cz;
                    $entity->boundingBox->setBB($axisalignedbb1);
                }else{
                    $entity->ySize += 0.5;
                }
            }
            $entity->x = ($entity->boundingBox->minX + $entity->boundingBox->maxX) / 2;
            $entity->y = $entity->boundingBox->minY - $entity->ySize;
            $entity->z = ($entity->boundingBox->minZ + $entity->boundingBox->maxZ) / 2;
            $entity->checkChunks();
            $entity->checkGroundState($movX, $movY, $movZ, $dx, $dy, $dz);
            $entity->updateFallState($dy, $entity->onGround);
            if($movX != $dx){
                $entity->motionX = 0;
            }
            if($movY != $dy){
                $entity->motionY = 0;
            }
            if($movZ != $dz){
                $entity->motionZ = 0;
            }
            //TODO: vehicle collision events (first we need to spawn them!)
            Timings::$entityMoveTimer->stopTiming();
            return true;
        }
    }

    /**
     * updateMovement
     * @param Entity $entity - as reference, so we are still on the origin entity object
     */
    public static function updateMovement(&$entity) {
        $diffPosition = ($entity->x - $entity->lastX) ** 2 + ($entity->y - $entity->lastY) ** 2 + ($entity->z - $entity->lastZ) ** 2;
        $diffRotation = ($entity->yaw - $entity->lastYaw) ** 2 + ($entity->pitch - $entity->lastPitch) ** 2;
        $diffMotion = ($entity->motionX - $entity->lastMotionX) ** 2 + ($entity->motionY - $entity->lastMotionY) ** 2 + ($entity->motionZ - $entity->lastMotionZ) ** 2;
        if($diffPosition > 0.04 or $diffRotation > 2.25 and ($diffMotion > 0.0001 and $entity->getMotion()->lengthSquared() <= 0.00001)){ //0.2 ** 2, 1.5 ** 2
            $entity->lastX = $entity->x;
            $entity->lastY = $entity->y;
            $entity->lastZ = $entity->z;
            $entity->lastYaw = $entity->yaw;
            $entity->lastPitch = $entity->pitch;
            $entity->level->addEntityMovement($entity->chunk->getX(), $entity->chunk->getZ(), $entity->id, $entity->x, $entity->y + $entity->getEyeHeight(), $entity->z, $entity->yaw, $entity->pitch, $entity->yaw);
        }
        if($diffMotion > 0.0025 or ($diffMotion > 0.0001 and $entity->getMotion()->lengthSquared() <= 0.0001)){ //0.05 ** 2
            $entity->lastMotionX = $entity->motionX;
            $entity->lastMotionY = $entity->motionY;
            $entity->lastMotionZ = $entity->motionZ;
            $entity->level->addEntityMotion($entity->chunk->getX(), $entity->chunk->getZ(), $entity->id, $entity->motionX, $entity->motionY, $entity->motionZ);
        }
    }
}