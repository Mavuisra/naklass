<?php
require_once '../includes/functions.php';
requireRole(['admin', 'direction', 'caissier']);
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$payment_id = intval($_GET['id'] ?? 0);
if (!$payment_id) { setFlashMessage('error','ID de paiement invalide.'); redirect('index.php'); }

$payment_query = "SELECT p.*, e.nom as eleve_nom, e.prenom as eleve_prenom, e.matricule, 
ec.nom_ecole as ecole_nom, ec.adresse as ecole_adresse, ec.ville as ecole_ville, ec.province as ecole_province, ec.pays as ecole_pays, ec.telephone as ecole_telephone, ec.email as ecole_email, u.nom as caissier_nom, u.prenom as caissier_prenom, c.nom_classe as classe_nom, c.niveau as classe_niveau 
FROM paiements p
JOIN eleves e ON p.eleve_id=e.id
JOIN ecoles ec ON e.ecole_id=ec.id
LEFT JOIN utilisateurs u ON p.caissier_id=u.id
LEFT JOIN inscriptions i ON e.id=i.eleve_id AND i.statut_inscription IN ('validée','en_cours')
LEFT JOIN classes c ON i.classe_id=c.id
WHERE p.id=:payment_id AND e.ecole_id=:ecole_id AND p.statut_record='actif'";
$payment_stmt = $db->prepare($payment_query);
$payment_stmt->execute(['payment_id'=>$payment_id,'ecole_id'=>$_SESSION['ecole_id']]);
$payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) { setFlashMessage('error','Paiement introuvable.'); redirect('index.php'); }

$lignes_query = "SELECT pl.*, tf.libelle as type_frais_nom FROM paiement_lignes pl JOIN types_frais tf ON pl.type_frais_id=tf.id WHERE pl.paiement_id=:payment_id ORDER BY tf.libelle";
$lignes_stmt = $db->prepare($lignes_query);
$lignes_stmt->execute(['payment_id'=>$payment_id]);
$lignes_paiement = $lignes_stmt->fetchAll(PDO::FETCH_ASSOC);

$mode_paiement = $payment['mode'] ?? $payment['mode_paiement'] ?? '';
$numero_recu = $payment['recu_numero'] ?? $payment['numero_recu'] ?? '';
$reference = $payment['reference_externe'] ?? $payment['reference_transaction'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Reçu <?php echo htmlspecialchars($numero_recu); ?></title>
<style>
body{font-family:'Arial',sans-serif;background:#f0f2f5;margin:0;padding:0;}
.container{position:relative;max-width:400px;margin:20px auto;padding:20px;background:white;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.1);}
.watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:3em;color:rgba(13,110,253,0.1);font-weight:bold;pointer-events:none;z-index:0;}
.header{text-align:center;border-bottom:3px solid #0d6efd;padding-bottom:10px;margin-bottom:15px;position:relative;z-index:1;}
.header h2{margin:0;color:#0d6efd;}
.info{margin-bottom:15px;position:relative;z-index:1;}
.info div{display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px dotted #ccc;}
.table{width:100%;border-collapse:collapse;margin-bottom:15px;position:relative;z-index:1;}
.table td{padding:5px;}
.table td.right{text-align:right;}
.total{background:#0d6efd;color:white;font-weight:bold;font-size:1.2em;padding:10px;text-align:right;border-radius:6px;position:relative;z-index:1;}
.button-print{display:inline-block;background:#0d6efd;color:white;padding:10px 20px;margin-bottom:10px;border:none;border-radius:6px;cursor:pointer;font-weight:bold;text-align:center;text-decoration:none;}
.button-print:hover{background:#0056b3;}
.signature-area{margin-top:20px;position:relative;z-index:1;}
.signature-box{border:1px solid #ccc;height:50px;border-radius:6px;background:#f8f9fa;margin-top:5px;padding:5px;}
.signature-label{font-size:0.9em;color:#555;}
.footer{margin-top:15px;font-size:0.8em;color:#555;text-align:center;position:relative;z-index:1;}
@media print{
  body{background:white;}
  .button-print{display:none;}
}
</style>
</head>
<body>
<div class="container">
  <div class="watermark"><?php echo htmlspecialchars($payment['ecole_nom']); ?></div>
  <button class="button-print" onclick="window.print()">🖨 Imprimer</button>
  <div class="header">
    <h2><?php echo htmlspecialchars($payment['ecole_nom']); ?></h2>
    <div style="font-size:12px;"><?php echo htmlspecialchars($payment['ecole_adresse'].', '.$payment['ecole_ville'].', '.$payment['ecole_province']); ?></div>
  </div>
  <div class="info">
    <div><span>Élève :</span><span><?php echo htmlspecialchars($payment['eleve_nom'].' '.$payment['eleve_prenom']); ?></span></div>
    <div><span>Classe :</span><span><?php echo htmlspecialchars($payment['classe_nom'] ?? ''); ?></span></div>
    <div><span>Matricule :</span><span><?php echo htmlspecialchars($payment['matricule']); ?></span></div>
    <div><span>Date :</span><span><?php echo formatDate($payment['date_paiement'],'d/m/Y H:i'); ?></span></div>
    <div><span>Mode :</span><span><?php
        $modes=['espèces'=>'Espèces','mobile_money'=>'Mobile Money','carte'=>'Carte bancaire','virement'=>'Virement','chèque'=>'Chèque'];
        echo $modes[$mode_paiement] ?? ucfirst($mode_paiement);
    ?></span></div>
    <?php if($reference): ?><div><span>Réf :</span><span><?php echo htmlspecialchars($reference); ?></span></div><?php endif; ?>
    <div><span>Reçu N° :</span><span><?php echo htmlspecialchars($numero_recu); ?></span></div>
  </div>
  <table class="table">
    <tbody>
      <?php foreach($lignes_paiement as $ligne): 
        $montant_ligne = $ligne['montant'] ?? $ligne['montant_ligne'] ?? 0; ?>
      <tr>
        <td><?php echo htmlspecialchars($ligne['type_frais_nom']); ?></td>
        <td class="right"><?php echo formatAmount($montant_ligne,$payment['monnaie']); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="total">TOTAL : <?php echo formatAmount($payment['montant_total'],$payment['monnaie']); ?></div>
  
  <div class="signature-area">
    <div class="signature-label">Signature du payeur</div>
    <div class="signature-box"></div>
    <div class="signature-label">Signature du caissier</div>
    <div class="signature-box"><?php echo htmlspecialchars($payment['caissier_nom'].' '.$payment['caissier_prenom']); ?></div>
  </div>

  <div class="footer">
    Reçu généré le <?php echo formatDate(date('Y-m-d H:i:s'),'d/m/Y H:i'); ?>
    <?php if(isset($_SESSION['nom']) && isset($_SESSION['prenom'])): ?>
    par <?php echo htmlspecialchars($_SESSION['nom'].' '.$_SESSION['prenom']); ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
