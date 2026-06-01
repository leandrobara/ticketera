<?php

namespace App\Helpers;


class AIPromptsHelper
{

    public const EXTRACT_LEAD_FROM_EMAIL_PROMPT = '
        Extraé la información de contacto del siguiente texto. 
        Debés buscar y devolver los siguientes campos si están presentes: nombre (name), apellido (last_name),
        email y teléfono (phone).
        Si algún dato no está presente, devolvé null. No inventes ni completes con supuestos. 
        A veces los datos pueden venir repetidos, desordenados o con etiquetas mal asignadas.

        IMPORTANTE: 
        - Siempre que un dato esté precedido por una etiqueta explícita (por ejemplo: "Nombre: Juan Pérez"), priorizalo
        por sobre cualquier deducción contextual. 
        - Esto es especialmente importante para los campos de nombre, apellido, email y teléfono, que pueden aparecer
        múltiples veces o en ubicaciones confusas. 

        ---

        CONTEXTO:
        Clienty CRM permite a sus clientes crear prospectos automáticamente a partir de emails
        enviados por servicios de terceros. 
        Estos servicios no siempre tienen API ni integración directa, por eso extraemos datos
        clave desde el cuerpo del email. 
        Este proceso se hace utilizando inteligencia artificial.

        ---

        FORMATO DE RESPUESTA ESPERADO (JSON ESTRICTO):
        {
            "success": bool,
            "data": {
                "name": string | null,
                "last_name": string | null,
                "email": string | null,
                "phone": string | null,
                "custom_variables": {
                    "xxxx": string | null,
                    "xxxx": string | null
                } | null
            },
            "error": string | null
        }

        REGLAS:
        - Si `"success"` es `true`, `"error"` debe ser `null`.
        - Si `"success"` es `false`, `"data"` debe ser un objeto vacio `null`,
        y `"error"` debe tener un mensaje de error en snake_case.
        - El campo `"custom_variables"` es opcional y sólo debe incluirse si se indica específicamente que hay variables
        adicionales a extraer. De lo contrario, debe ser `null`.
        - Si se te pide extraer un campo adicional personalizado, incluí su nombre como clave en `"custom_variables"` y
        devolvé su valor o `null` si no lo encontrás. Por lo tanto siempre que se pida extraer un dato adicional tenemos
        que agregarlo siempre como clave en `"custom_variables"` con valor o null en caso de no encontrar un valor
        siguiendo estrictamente las instrucciones dadas para extraer ese valor.
        - Siempre que se te pida extraer un campo adicional (custom field), debes incluir su nombre como clave dentro de
        custom_variables, sin excepciones.
        Si no lográs encontrar el valor, asigná null a esa clave.
        Por ejemplo, si se te pide "codigo_aviso" y no aparece en el texto, la respuesta correcta es:
        "custom_variables": { "codigo_aviso": null }.
        Nunca omitas una clave pedida, aunque no hayas encontrado el valor.
        - El atributo data.custom_variables es opcional, y existe solamente si se te pide que extraigas
        algún dato que NO sea de
        los 4 que existen en data (name, last_name, email, phone). De no pedirtelo,
        debe ser null (data.custom_variables: null).
        - Si NO hay ninguna etiqueta explícita para nombre o apellido, podés deducirlos del email, por ejemplo:
        -- Si el email es juanperez@gmail.com y no hay etiquetas explícitas,
        podés inferir "name": "Juan" y "last_name": "Perez".
        -- Si no podés inferir claramente el nombre y apellido desde el email
        (por ejemplo, si es algo como jp_1984@gmail.com), entonces devolvé null.

        EVALUACION INICIAL:
        Antes de intentar extraer ningún dato, analizá si el contenido del email realmente
        refiere a un evento que implica un nuevo prospecto.  
        Si no es así (por ejemplo, es un mensaje administrativo o sin datos de contacto),
        devolvé lo siguiente:

        {
            "success": false,
            "data": null,
            "error": "email_text_does_not_seem_to_be_a_lead"
        }

        ---

        INSTRUCCIONES FINALES:
        - Respondé únicamente con el objeto JSON.
        - No incluyas encabezados, texto explicativo ni bloques de código como ```json.
        - Tu salida debe comenzar y terminar exclusivamente con `{ ... }`.

        ---

        Texto del email:
        """
        {{EMAIL}}
        """
        
        """
        {{EXTRA_DATA}}
        """

        """
        {{EXTRA_PROMPT}}
        """
    ';

}
