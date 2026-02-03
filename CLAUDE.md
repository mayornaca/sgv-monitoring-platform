<!-- OPENSPEC:START -->
# OpenSpec Instructions

These instructions are for AI assistants working in this project.

Always open `@/openspec/AGENTS.md` when the request:
- Mentions planning or proposals (words like proposal, spec, change, plan)
- Introduces new capabilities, breaking changes, architecture shifts, or big performance/security work
- Sounds ambiguous and you need the authoritative spec before coding

Use `@/openspec/AGENTS.md` to learn:
- How to create and apply change proposals
- Spec format and conventions
- Project structure and guidelines

Keep this managed block so 'openspec update' can refresh the instructions.

<!-- OPENSPEC:END -->

- Add to memory el proyecto legacy se encuentra dentro de este mismo proyecto en old-project
- Add to memory no uses iconos a menos que lo pida
- Add to memory los comentarios en el código deben hacerse de forma genérica no como un agente de IA
- Add to memory Re-validación de números WhatsApp: Usar comando `php bin/console app:whatsapp:revalidate <PIN>` cuando un número tenga estado EXPIRED. Ver docs/WHATSAPP_REVALIDATION.md para instrucciones completas
- Add to memory Permisos archivos .env: SIEMPRE deben ser `opc:www` con permisos `640`. Después de editar .env ejecutar: `sudo chown opc:www .env .env.prod`. Ver docs/FILE_PERMISSIONS.md
- Add to memory investiga avances que podamos reutilizar, refactorizar y en definitiva no duplicar/triplicar esfuersos, no crear codigo redundante y spaghetti, no desviar el curso llenando de interfaces visuales no profesionales ni
  siendo lazy ni hacky, siempre debes mantener las buenas prácticas del framework y el stack completo,, no evites comparar siempre con legacy considerando de que es un sistema que funcionaba pero que cambio la api a la actual que
  tenemos, siempre usa buenas práctucas y busca documentación oficial y patron de diseño en la web, casos de exito,