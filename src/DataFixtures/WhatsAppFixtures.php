<?php

namespace App\DataFixtures;

use App\Entity\WhatsApp\Recipient;
use App\Entity\WhatsApp\RecipientGroup;
use App\Entity\WhatsApp\Template;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class WhatsAppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 1. Crear recipient principal
        $jonathan = new Recipient();
        $jonathan->setNombre('Jonathan Nacaratto');
        $jonathan->setTelefono('+56972126016');
        $jonathan->setEmail('jnacaratto@gesvial.cl');
        $jonathan->setActivo(true);
        $jonathan->setNotas('Destinatario principal para alertas de Prometheus y COT');
        $manager->persist($jonathan);

        // 2. Crear grupos de destinatarios
        $prometheusGroup = new RecipientGroup();
        $prometheusGroup->setNombre('Alertas Prometheus');
        $prometheusGroup->setSlug('prometheus_alerts');
        $prometheusGroup->setDescripcion('Grupo para recibir alertas críticas de Prometheus');
        $prometheusGroup->setActivo(true);
        $prometheusGroup->addRecipient($jonathan);
        $manager->persist($prometheusGroup);

        $spireGroup = new RecipientGroup();
        $spireGroup->setNombre('Alertas Espiras COT');
        $spireGroup->setSlug('spire_alerts');
        $spireGroup->setDescripcion('Grupo para recibir alertas de pérdida de datos en espiras');
        $spireGroup->setActivo(true);
        $spireGroup->addRecipient($jonathan);
        $manager->persist($spireGroup);

        // 3. Crear template para Prometheus
        $prometheusTemplate = new Template();
        $prometheusTemplate->setNombre('Alerta Prometheus Firing');
        $prometheusTemplate->setMetaTemplateId('prometheus_alert_firing');
        $prometheusTemplate->setParametrosCount(4);
        $prometheusTemplate->setParametrosDescripcion([
            'var1' => 'Nombre de la alerta (alertname)',
            'var2' => 'Severidad (severity)',
            'var3' => 'Resumen de la alerta (summary)',
            'var4' => 'Instancia o dispositivo afectado (instance/job)'
        ]);
        $prometheusTemplate->setLanguage('es');
        $prometheusTemplate->setActivo(true);
        $prometheusTemplate->setDescripcion('Template aprobado en Meta para alertas de Prometheus. Solo se envía cuando severity=critical y status=firing');
        $manager->persist($prometheusTemplate);

        // 4. Crear template para Espiras COT
        $spireTemplate = new Template();
        $spireTemplate->setNombre('Alerta Pérdida Datos Espiras');
        $spireTemplate->setMetaTemplateId('card_transaction_alert_1');
        $spireTemplate->setParametrosCount(4);
        $spireTemplate->setParametrosDescripcion([
            'var1' => 'Período analizado (ej: "00:00 a 15:30 del 23-10-2025")',
            'var2' => 'Primer dispositivo con problema',
            'var3' => 'Segundo dispositivo con problema',
            'var4' => 'Tercer dispositivo con problema'
        ]);
        $spireTemplate->setLanguage('es');
        $spireTemplate->setActivo(true);
        $spireTemplate->setDescripcion('Template aprobado en Meta para alertas de espiras con pérdida de datos >= 3%. Usa hash-based deduplication.');
        $manager->persist($spireTemplate);

        $manager->flush();
    }
}
