/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import { Payload, Method, Model, Entity, Action, ResponseMsg, EntityType, Target } from './interfaces';
import { Ajax } from './Ajax.class';

export default class Link {
  entity: Entity;
  model: Model;
  sender: Ajax;

  constructor(entity: Entity) {
    this.entity = entity;
    this.model = Model.Link,
    this.sender = new Ajax();
  }

  create(targetId: number, targetEntity: EntityType = EntityType.Item): Promise<ResponseMsg> {
    const payload: Payload = {
      method: Method.POST,
      action: Action.Create,
      model: this.model,
      entity: this.entity,
      content: String(targetId),
      extraParams: { targetEntity: targetEntity },
    };
    return this.sender.send(payload);
  }

  destroy(id: number, targetEntity: EntityType = EntityType.Item): Promise<ResponseMsg> {
    const payload: Payload = {
      method: Method.POST,
      action: Action.Destroy,
      model: this.model,
      entity: this.entity,
      id: id,
      extraParams: { targetEntity: targetEntity },
    };
    return this.sender.send(payload);
  }

  importLinks(id: number, targetEntity: EntityType = EntityType.Item): Promise<ResponseMsg> {
    const payload: Payload = {
      method: Method.POST,
      action: Action.ImportLinks,
      model: this.model,
      entity: this.entity,
      // convert EntityType.Item/Experiment to Target.LinkedItems/LinkedExperiments
      target: targetEntity as unknown as Target,
      id: id,
      notif: true,
    };
    return this.sender.send(payload);
  }
}
