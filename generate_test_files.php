<?php

$dir = __DIR__ . '/test_files_fondation';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$files = [
    'dossier_medical_001.txt' => "Fondation Cheikh Zayed\nService: Cardiologie\nPatient ID: 100452\nNom: ALAMI Ahmed\nNotes: Patient admis pour arythmie cardiaque. Antécédents d'hypertension. Traitement prescrit.",
    
    'resultats_analyse_sang.csv' => "Paramètre,Valeur,Unité,Norme\nGlycémie,1.05,g/L,0.70-1.10\nCholestérol,2.10,g/L,<2.00\nHémoglobine,14.5,g/dL,13.0-17.0\nLeucocytes,6.5,G/L,4.0-10.0",
    
    'ordonnance_1204.md' => "# Ordonnance Médicale\n**Dr. Lahlou M.**\n*Fondation Cheikh Zayed*\nDate: 2026-04-20\n\n1. Paracétamol 1000mg - 1 cp 3x/jour\n2. Amoxicilline 500mg - 1 cp 2x/jour pendant 7 jours\n\nSignature: _________",
    
    'rapport_radiologie.txt' => "RAPPORT DE RADIOLOGIE - FONDATION CHEIKH ZAYED\n=========================================\nExamen: IRM Cérébrale\nDate: 15/04/2026\nPatient: BENNANI Khadija\n\nConclusion: Aucune anomalie décelée. Les structures cérébrales sont d'aspect normal. Pas de signe d'ischémie.",
    
    'facturation_sejour.xml' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<facture>\n    <hopital>Fondation Cheikh Zayed</hopital>\n    <patient>ID_9984</patient>\n    <montant>15000.00</montant>\n    <devise>MAD</devise>\n    <details>\n        <item nom=\"Chambre standard (3 nuits)\" prix=\"3000.00\" />\n        <item nom=\"Intervention Chirurgicale\" prix=\"10000.00\" />\n        <item nom=\"Frais de Pharmacie\" prix=\"2000.00\" />\n    </details>\n</facture>",
    
    'historique_consultations.json' => "{\n  \"hopital\": \"Fondation Cheikh Zayed\",\n  \"departement\": \"Pédiatrie\",\n  \"consultations\": [\n    {\"date\": \"2026-01-10\", \"medecin\": \"Dr. Tazi\", \"motif\": \"Fièvre\"},\n    {\"date\": \"2026-03-05\", \"medecin\": \"Dr. Tazi\", \"motif\": \"Vaccination de routine\"}\n  ]\n}",
    
    'certificat_medical.txt' => "Je soussigné, Dr. El Fassi, exerçant à la Fondation Cheikh Zayed, certifie que l'état de santé de M. Youssef nécessite un repos strict de 5 jours à compter de la date d'aujourd'hui.\n\nFait pour servir et valoir ce que de droit.",
    
    'liste_patients_jour.csv' => "Heure_RDV,Nom_Patient,Medecin,Statut\n09:00,Rachid M.,Dr. Chraibi,En Attente\n09:30,Nawal A.,Dr. Chraibi,En Consultation\n10:00,Hassan B.,Dr. Chraibi,Confirmé\n10:30,Sara K.,Dr. Chraibi,Annulé",
    
    'consentement_eclaire.md' => "# Formulaire de Consentement Éclairé\n\n**Établissement**: Fondation Cheikh Zayed\n\nJe soussigné(e), déclare avoir reçu les informations nécessaires concernant l'intervention chirurgicale (Appendicectomie). Les risques (infection, saignements, risques liés à l'anesthésie) m'ont été expliqués par le Dr. Ziyad.\n\n*Signature du patient:*\n*Date:* 20/04/2026",
    
    'notes_chirurgicales.txt' => "BLOC OPÉRATOIRE - SALLE 4\nIntervention: Cholecystectomie laparoscopique\nDébut: 10h15\nFin: 11h30\nChirurgien: Dr. Boujida\nAnesthésiste: Dr. Naciri\n\nNotes de l'intervention: L'intervention s'est déroulée sans complications. La vésicule présentait une inflammation chronique avec présence de multiples calculs. Ablation réussie, hémostase soigneuse. Transfert en salle de réveil à 11h40, patient stable."
];

foreach ($files as $filename => $content) {
    file_put_contents($dir . '/' . $filename, $content);
}

echo "10 realistic test files generated successfully in: $dir\n";
