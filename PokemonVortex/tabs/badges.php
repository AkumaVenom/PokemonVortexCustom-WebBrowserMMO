<?php
include(__DIR__ . '/../kick.php');
if(!$_SESSION['myid'] || $_SESSION['access'] != 9){ // Check the user is logged in
	echo '<center><h3>Your session has expired, please log back in.</h3></center>';
	exit();
}
include(__DIR__ . '/../pv_connect_to_db.php');
$bad = mysql_query("SELECT * FROM badges WHERE id = '{$_REQUEST['myid']}'"); // Get gym badge info
$ad = mysql_fetch_array($bad);
$acc = mysql_query("SELECT * FROM members WHERE id = '{$_REQUEST['myid']}'"); // Get member info
$ac = mysql_fetch_array($acc);
$eve = mysql_query("SELECT * FROM events WHERE id = '{$_REQUEST['myid']}'"); // Get event badge info
$ev = mysql_fetch_array($eve);
$acs = mysql_query("SELECT * FROM members_options WHERE id = '{$_REQUEST['myid']}'"); // Get more member info
$as = mysql_fetch_array($acs);
?>

<center><h4><?php echo '' . $ac['username'] . '\'s Badges</h4>'; ?></center>
<table width="600" style="text-align:center;"><tr><td style="vertical-align: top; width:200px;">

<!-- Kanto badges -->

<p><strong>Indigo League Badges:</strong><ul style="line-height: 150%">
<?php if($ad['g1'] == 1){?>
<li>Boulder <img src="html/static/images/badges/boulder.gif" align="absmiddle" /></li>
<?php } if($ad['g2'] == 1){?>
<li>Cascade <img src="html/static/images/badges/cascade.gif" align="absmiddle" /></li>
<?php } if($ad['g3'] == 1){?>
<li>Thunder <img src="html/static/images/badges/thunder.gif" align="absmiddle" /></li>
<?php } if($ad['g4'] == 1){?>
<li>Rainbow <img src="html/static/images/badges/rainbow.gif" align="absmiddle" /></li>
<?php } if($ad['g5'] == 1){?>
<li>Marsh <img src="html/static/images/badges/marsh.gif" align="absmiddle" /></li>
<?php } if($ad['g6'] == 1){?>
<li>Soul <img src="html/static/images/badges/soul.gif" align="absmiddle" /></li>
<?php } if($ad['g7'] == 1){?>
<li>Volcano <img src="html/static/images/badges/volcano.gif" align="absmiddle" /></li>
<?php } if($ad['g8'] == 1){?>
<li>Earth <img src="html/static/images/badges/earth.gif" align="absmiddle" /></li>
<?php } ?></ul></p></td><td style="vertical-align: top; width:200px;">

<!-- Orange Island badges -->

<p><strong>Orange Island Badges:</strong><ul style="line-height: 150%">
<?php if($ad['g9'] == 1){?>
<li>Coral-Eye <img src="html/static/images/badges/coral.gif" align="absmiddle" /></li>
<?php } if($ad['g10'] == 1){?>
<li>Sea Ruby <img src="html/static/images/badges/ruby.gif" align="absmiddle" /></li>
<?php } if($ad['g11'] == 1){?>
<li>Spike Shell <img src="html/static/images/badges/spike.gif" align="absmiddle" /></li>
<?php } if($ad['g12'] == 1){?>
<li>Jade Star <img src="html/static/images/badges/jade.gif" align="absmiddle" /></li>
<?php } if($ad['g13'] == 1){?>
<li>Winners Trophy <img src="html/static/images/badges/winners trophy.gif" align="absmiddle" /></li>
<?php } ?></ul></p></td><td style="vertical-align: top; width:200px;">

<!-- Johto badges -->

<p><strong>Johto League Badges:</strong><ul style="line-height: 150%">
<?php if($ad['g14'] == 1){?>
<li>Zephyr <img src="html/static/images/badges/zephyr.gif" align="absmiddle" /></li>
<?php } if($ad['g15'] == 1){?>
<li>Hive <img src="html/static/images/badges/hive.gif" align="absmiddle" /></li>
<?php } if($ad['g16'] == 1){?>
<li>Plain <img src="html/static/images/badges/plain.gif" align="absmiddle" /></li>
<?php } if($ad['g17'] == 1){?>
<li>Fog <img src="html/static/images/badges/fog.gif" align="absmiddle" /></li>
<?php } if($ad['g18'] == 1){?>
<li>Storm <img src="html/static/images/badges/storm.gif" align="absmiddle" /></li>
<?php } if($ad['g19'] == 1){?>
<li>Mineral <img src="html/static/images/badges/mineral.gif" align="absmiddle" /></li>
<?php } if($ad['g20'] == 1){?>
<li>Glacier <img src="html/static/images/badges/glacier.gif" align="absmiddle" /></li>
<?php } if($ad['g21'] == 1){?>
<li>Rising <img src="html/static/images/badges/rising.gif" align="absmiddle" /></li><?php } ?></ul></p></td></tr><tr><td style="vertical-align: top; width:200px;">

<!-- Hoenn badges -->

<p><strong>Hoenn League Badges:</strong><ul style="line-height: 150%">
<?php if($ad['g22'] == 1){?>
<li>Stone <img src="html/static/images/badges/stone.gif" align="absmiddle" /></li>
<?php } if($ad['g23'] == 1){?>
<li>Knuckle <img src="html/static/images/badges/knuckle.gif" align="absmiddle" /></li>
<?php } if($ad['g24'] == 1){?>
<li>Dynamo <img src="html/static/images/badges/dynamo.gif" align="absmiddle" /></li>
<?php } if($ad['g25'] == 1){?>
<li>Heat <img src="html/static/images/badges/heat.gif" align="absmiddle" /></li>
<?php } if($ad['g26'] == 1){?>
<li>Balance <img src="html/static/images/badges/balance.gif" align="absmiddle" /></li>
<?php } if($ad['g27'] == 1){?>
<li>Feather <img src="html/static/images/badges/feather.gif" align="absmiddle" /></li>
<?php } if($ad['g28'] == 1){?>
<li>Mind <img src="html/static/images/badges/mind.gif" align="absmiddle" /></li>
<?php } if($ad['g64'] == 1){?>
<li>Rain <img src="html/static/images/badges/rain.gif" align="absmiddle" /></li>
<?php } ?></ul></p></td><td style="vertical-align: top; width:200px;">

<!-- Sinnoh badges -->

<p><strong>Sinnoh League Badges:</strong><ul style="line-height: 150%">
<?php if($ad['g30'] == 1){?>
<li>Coal <img src="html/static/images/badges/coal.gif" align="absmiddle" /></li>
<?php } if($ad['g31'] == 1){?>
<li>Forest <img src="html/static/images/badges/forest.gif" align="absmiddle" /></li>
<?php } if($ad['g32'] == 1){?>
<li>Cobble <img src="html/static/images/badges/cobble.gif" align="absmiddle" /></li>
<?php } if($ad['g33'] == 1){?>
<li>Fen <img src="html/static/images/badges/fen.gif" align="absmiddle" /></li>
<?php } if($ad['g34'] == 1){?>
<li>Relic <img src="html/static/images/badges/relic.gif" align="absmiddle" /></li>
<?php } if($ad['g35'] == 1){?>
<li>Mine <img src="html/static/images/badges/mine.gif" align="absmiddle" /></li>
<?php } if($ad['g36'] == 1){?>
<li>Icicle <img src="html/static/images/badges/icicle.gif" align="absmiddle" /></li>
<?php } if($ad['g37'] == 1){?>
<li>Beacon <img src="html/static/images/badges/beacon.gif" align="absmiddle" /></li>
<?php }?></ul></p></td><td style="vertical-align: top; width:200px;">

<!-- Unova badges -->

<p><strong>Unova League Badges:</strong><ul style="line-height: 150%">
<?php if($ad['g38'] == 1){?>
<li>Basic <img src="html/static/images/badges/basic.gif" align="absmiddle" /></li>
<?php } if($ad['g39'] == 1){?>
<li>Toxic <img src="html/static/images/badges/toxic.gif" align="absmiddle" /></li>
<?php } if($ad['g40'] == 1){?>
<li>Beetle <img src="html/static/images/badges/beetle.gif" align="absmiddle" /></li>
<?php } if($ad['g41'] == 1){?>
<li>Bolt <img src="html/static/images/badges/bolt.gif" align="absmiddle" /></li>
<?php } if($ad['g42'] == 1){?>
<li>Quake <img src="html/static/images/badges/quake.gif" align="absmiddle" /></li>
<?php } if($ad['g43'] == 1){?>
<li>Jet <img src="html/static/images/badges/jet.gif" align="absmiddle" /></li>
<?php } if($ad['g44'] == 1){?>
<li>Legend <img src="html/static/images/badges/legend.gif" align="absmiddle" /></li>
<?php } if($ad['g45'] == 1){?>
<li>Wave <img src="html/static/images/badges/wave.gif" align="absmiddle" /></li>
<?php }?></ul></td></tr><tr><td style="vertical-align: top; width:200px;">

<!-- Kalos badges -->
<p><strong>Kalos League Badges:</strong><ul style="line-height: 150%">
<?php if($ad['g79'] == 1){?>
<li>Bug <img src="html/static/images/badges/bug.gif" align="absmiddle" /></li>
<?php } if($ad['g80'] == 1){?>
<li>Cliff <img src="html/static/images/badges/cliff.gif" align="absmiddle" /></li>
<?php } if($ad['g81'] == 1){?>
<li>Rumble <img src="html/static/images/badges/rumble.gif" align="absmiddle" /></li>
<?php } if($ad['g82'] == 1){?>
<li>Plant <img src="html/static/images/badges/plant.gif" align="absmiddle" /></li>
<?php } if($ad['g83'] == 1){?>
<li>Voltage <img src="html/static/images/badges/voltage.gif" align="absmiddle" /></li>
<?php } if($ad['g84'] == 1){?>
<li>Fairy <img src="html/static/images/badges/fairy.gif" align="absmiddle" /></li>
<?php } if($ad['g85'] == 1){?>
<li>Psychic <img src="html/static/images/badges/psychic.gif" align="absmiddle" /></li>
<?php } if($ad['g86'] == 1){?>
<li>Iceberg <img src="html/static/images/badges/iceberg.gif" align="absmiddle" /></li>
<?php }?></ul></td><td style="vertical-align: top; width:200px;">

<!-- Hoenn battle frontier -->

<p><strong>Hoenn Frontier Symbols:</strong><ul style="line-height: 150%">
<?php if($ad['g67'] == 1){?>
<li>Ability <img src="html/static/images/badges/ability symbol.gif" /></li>
<?php } if($ad['g68'] == 1){?>
<li>Spirit <img src="html/static/images/badges/spirit symbol.gif" /></li>
<?php } if($ad['g69'] == 1){?>
<li>Knowledge <img src="html/static/images/badges/knowledge symbol.gif" /></li>
<?php } if($ad['g70'] == 1){?>
<li>Brave <img src="html/static/images/badges/brave symbol.gif" /></li>
<?php } if($ad['g71'] == 1){?>
<li>Tactics <img src="html/static/images/badges/tactics symbol.gif" /></li>
<?php } if($ad['g72'] == 1){?>
<li>Guts <img src="html/static/images/badges/guts symbol.gif" /></li>
<?php } if($ad['g73'] == 1){?>
<li>Luck <img src="html/static/images/badges/luck symbol.gif" /></li><?php } ?></ul></p></td><td style="vertical-align: top; width:200px;">

<!-- Sinnoh battle frontier -->

<p><strong>Sinnoh Frontier Symbols:</strong><ul style="line-height: 150%">
<?php if($ad['g74'] == 1){?>
<li>Palmer <img src="html/static/images/badges/palmer symbol.gif" /></li>
<?php } if($ad['g75'] == 1){?>
<li>Thorton <img src="html/static/images/badges/thorton symbol.gif" /></li>
<?php } if($ad['g76'] == 1){?>
<li>Dahlia <img src="html/static/images/badges/dahlia symbol.gif" /></li>
<?php } if($ad['g77'] == 1){?>
<li>Darach <img src="html/static/images/badges/darach symbol.gif" /></li>
<?php } if($ad['g78'] == 1){?>
<li>Argenta <img src="html/static/images/badges/argenta symbol.gif" /></li><?php } ?></ul></p></td></tr><tr><td style="vertical-align: top; width:200px;">


<!-- Kanto & Johto elite 4s -->

<p><strong>Indigo/Johto Elite Four:</strong><ul style="line-height: 150%">
<?php if($ad['g46'] == 1){?><li>Will</li>
<?php } if($ad['g47'] == 1){?><li>Koga</li>
<?php } if($ad['g48'] == 1){?><li>Bruno</li>
<?php } if($ad['g49'] == 1){?><li>Karen</li><?php } ?></ul></p></td><td style="vertical-align: top; width:200px;">

<!-- Hoenn elite 4s -->

<p><strong>Hoenn Elite Four:</strong><ul style="line-height: 150%">
<?php if($ad['g50'] == 1){?><li>Sidney</li>
<?php } if($ad['g51'] == 1){?><li>Phoebe</li>
<?php } if($ad['g52'] == 1){?><li>Glacia</li>
<?php } if($ad['g53'] == 1){?><li>Drake</li><?php } ?></ul></p></td><td style="vertical-align: top; width:200px;">

<!-- Sinnoh elite 4s -->

<p><strong>Sinnoh Elite Four:</strong><ul style="line-height: 150%">
<?php if($ad['g54'] == 1){?><li>Aaron</li>
<?php } if($ad['g55'] == 1){?><li>Bertha</li>
<?php } if($ad['g56'] == 1){?><li>Flint</li>
<?php } if($ad['g57'] == 1){?><li>Lucian</li><?php } ?></ul></p></td></tr><tr><td style="vertical-align: top; width:200px;">

<!-- Unova elite 4s -->

<p><strong>Unova Elite Four:</strong><ul style="line-height: 150%">
<?php if($ad['g58'] == 1){?><li>Shauntal</li>
<?php } if($ad['g59'] == 1){?><li>Grimsley</li>
<?php } if($ad['g60'] == 1){?><li>Caitlin</li>
<?php } if($ad['g61'] == 1){?><li>Marshal</li><?php } ?></ul></p></td><td style="vertical-align: top; width:200px;">

<!-- Kalos elite 4s -->

<p><strong>Kalos Elite Four:</strong><ul style="line-height: 150%">
<?php if($ad['g87'] == 1){?><li>Malva</li>
<?php } if($ad['g88'] == 1){?><li>Wikstrom</li>
<?php } if($ad['g89'] == 1){?><li>Drasna</li>
<?php } if($ad['g90'] == 1){?><li>Siebold</li><?php } ?></ul></p></td><td style="vertical-align: top; width:200px;">

<!-- Kalos Battle Maison -->

<p><strong>Kalos Battle Maison:</strong><ul style="line-height: 150%">
<?php if($ad['g92'] == 1){?><li>Chatelaine Nita</li>
<?php } if($ad['g93'] == 1){?><li>Chatelaine Evelyn</li>
<?php } if($ad['g94'] == 1){?><li>Chatelaine Dana</li>
<?php } if($ad['g95'] == 1){?><li>Chatelaine Morgan</li><?php } ?></ul></p></td></tr><tr><td style="vertical-align: top; width:200px;">

<!-- Champions -->

<p><strong>Champions Defeated:</strong><ul style="line-height: 150%">
<?php if($ad['g62'] == 1){?><li>Blue</li>
<?php } if($ad['g63'] == 1){?><li>Lance</li>
<?php } if($ad['g29'] == 1){?><li>Wallace</li>
<?php } if($ad['g65'] == 1){?><li>Cynthia</li>
<?php } if($ad['g66'] == 1){?><li>Iris</li>
<?php } if($ad['g91'] == 1){?><li>Diantha</li><?php } ?></ul></p></td></tr></table>

<!-- Events -->

<p><center><strong>Events:</strong></p>
<?php if($ev['g31'] == 1){?> <img src="html/static/images/specialbadges/santabadge.gif" TITLE="Christmas 2014" /><?php } ?>
<?php if($ev['g38'] == 1){?> <img src="html/static/images/specialbadges/halloween2014.gif" TITLE="Halloween 2014" /><?php } ?>
<?php if($ev['g1'] == 1 && $ev['g2'] == 1 && $ev['g3'] == 1 && $ev['g4'] == 1 && $ev['g5'] == 1){?> <img src="html/static/images/specialbadges/rocket.gif" TITLE="Team Rocket" /><?php } ?>
<?php if($ev['g6'] == 1 && $ev['g7'] == 1 && $ev['g8'] == 1 && $ev['g9'] == 1 && $ev['g10'] == 1){?> <img src="html/static/images/specialbadges/aqua.gif" TITLE="Team Aqua" /><?php } ?>
<?php if($ev['g11'] == 1 && $ev['g12'] == 1 && $ev['g13'] == 1 && $ev['g14'] == 1 && $ev['g15'] == 1){?> <img src="html/static/images/specialbadges/magma.gif" TITLE="Team Magma" /><?php } ?>
<?php if($ev['g16'] == 1 && $ev['g17'] == 1 && $ev['g18'] == 1 && $ev['g19'] == 1 && $ev['g20'] == 1){?> <img src="html/static/images/specialbadges/galactic.gif" TITLE="Team Galactic" /><?php } ?>
<?php if($ev['g21'] == 1 && $ev['g22'] == 1 && $ev['g23'] == 1 && $ev['g24'] == 1){?> <img src="html/static/images/specialbadges/plasma.gif" TITLE="Team Plasma" /><?php } ?>
<?php if($ev['g32'] == 1 && $ev['g33'] == 1 && $ev['g34'] == 1 && $ev['g35'] == 1 && $ev['g36'] == 1 && $ev['g37'] == 1){?> <img src="html/static/images/specialbadges/flare.gif" TITLE="Team Flare" /><?php } ?>
<?php if($ev['g26'] == 1 && $ev['g27'] == 1){?> <img src="html/static/images/specialbadges/admins.gif" TITLE="Vortex Staff Challenge" /><?php } ?>
<?php if($ev['g28'] == 1){?> <img src="html/static/images/specialbadges/xd.gif" TITLE="Cipher" /><?php } ?>
<?php if($ev['g29'] == 1){?> <img src="html/static/images/specialbadges/gordor.gif" TITLE="Gordor" /><?php } ?>
<?php if($ev['g30'] == 1){?> <img src="html/static/images/specialbadges/pokemon4ever.gif" TITLE="Iron-Masked Marauder" /><?php } ?></center>

<!-- Achievements -->

<p><center><p><strong>Achievements:</strong></p>

<!-- Win related achievements -->

<?php if($ac['battle'] >= 100){?><img src="html/static/images/achievements/v3/100wins.gif" TITLE="100 Wins" />
<?php } if($ac['battle'] >= 250){?><img src="html/static/images/achievements/v3/250wins.gif" TITLE="250 Wins" />
<?php } if($ac['battle'] >= 500){?><img src="html/static/images/achievements/v3/500wins.gif" TITLE="500 Wins" />
<?php } if($ac['battle'] >= 1000){?><img src="html/static/images/achievements/v3/1000wins.gif" TITLE="1,000 Wins" />
<?php } if($ac['battle'] >= 10000){?><img src="html/static/images/achievements/v3/10000wins.gif" TITLE="10,000 Wins" /><?php } ?>

<!-- Unique Pokemon related achievements -->

<?php if($ac['uniques'] >= 1000){?><img src="html/static/images/achievements/v3/1000uniques.gif" TITLE="1,000 Unique Pok&eacute;mon" />
<?php } if($ac['uniques'] >= 2000){?><img src="html/static/images/achievements/v3/2000uniques.gif" TITLE="2,000 Unique Pok&eacute;mon" />
<?php } if($ac['uniques'] >= 3000){?><img src="html/static/images/badges/winners trophy.gif" TITLE="3,000 Unique Pok&eacute;mon" />
<?php } if($ac['uniques'] >= 4000){?><img src="html/static/images/badges/winners trophy.gif" TITLE="4,000 Unique Pok&eacute;mon" />
<?php } if($ac['uniques'] >= 5394){?><img src="html/static/images/achievements/v3/completedex.gif" TITLE="Complete Pok&eacute;dex" /><?php } ?>

<!-- Total experience related achievements -->

<?php if($ac['totalexp'] >= 10000000){?><img src="html/static/images/achievements/v3/10milexp.gif" TITLE="10,000,000 Experience" />
<?php } if($ac['totalexp'] >= 50000000){?><img src="html/static/images/achievements/v3/50milexp.gif" TITLE="50,000,000 Experience" />
<?php } if($ac['totalexp'] >= 100000000){?><img src="html/static/images/achievements/v3/100milexp.gif" TITLE="100,000,000 Experience" />
<?php } if($ac['totalexp'] >= 200000000){?><img src="html/static/images/achievements/v3/200milexp.gif" TITLE="200,000,000 Experience" />
<?php } if($ac['totalexp'] >= 500000000){?><img src="html/static/images/achievements/v3/500milexp.gif" TITLE="500,000,000 Experience" />

<!-- Average experience related achievements -->

<?php } if($ac['averageexp'] >= 100000){?><img src="html/static/images/achievements/v3/100000avexp.gif" TITLE="100,000 Average Experience" />
<?php } if($ac['averageexp'] >= 200000){?><img src="html/static/images/achievements/v3/200000avexp.gif" TITLE="200,000 Average Experience" />
<?php } if($ac['averageexp'] >= 500000){?><img src="html/static/images/achievements/v3/500000avexp.gif" TITLE="500,000 Average Experience" /><?php } ?>

<!-- Badges related achievements -->

<?php if($ac['badges'] == 1){?><img src="html/static/images/achievements/v3/leaguechampion.gif" TITLE="All Gyms, Elite 4's, Champions & Battle Frontiers Defeated" />
<?php } if($ad['g68'] == 1 && $ad['g69'] == 1 && $ad['g70'] == 1 && $ad['g71'] == 1 && $ad['g72'] == 1 && $ad['g73'] == 1 && $ad['g74'] == 1){?><img src="html/static/images/achievements/v3/hoennfrontier.gif" TITLE="Hoenn Battle Frontier Completed" />
<?php } if($ad['g75'] == 1 && $ad['g76'] == 1 && $ad['g77'] == 1 && $ad['g78'] == 1 && $ad['g79'] == 1){?><img src="html/static/images/achievements/v3/sinnohfrontier.gif" TITLE="Sinnoh Battle Frontier Completed" />
<?php } if($ac['sidequest'] >= 103){?><img src="html/static/images/achievements/v3/kantosidequests.gif" TITLE="Kanto Sidequests Complete" />
<?php } if($ac['sidequest'] >= 207){?><img src="html/static/images/achievements/v3/johtosidequests.gif" TITLE="Johto Sidequests Complete" />
<?php } if($ac['sidequest'] >= 308){?><img src="html/static/images/achievements/v3/seviiislandssidequests.gif" TITLE="Sevii Islands Sidequests Complete" />
<?php } if($ac['sidequest'] >= 362){?><img src="html/static/images/achievements/v3/tcgsidequests.gif" TITLE="TCG Sidequests Complete" />
<?php } if($ac['sidequest'] >= 483){?><img src="html/static/images/achievements/v3/orangeislandssidequests.gif" TITLE="Orange Islands Sidequests Complete" />
<?php } if($ac['sidequest'] >= 696){?><img src="html/static/images/achievements/v3/hoennsidequests.gif" TITLE="Hoenn Sidequests Complete" />
<?php } ?>

<!-- Points related achievements -->

<?php if($ac['points'] > 1000){?><img src="html/static/images/achievements/v3/1000points.gif" TITLE="1,000 Points" />
<?php } if($ac['points'] > 10000){?><img src="html/static/images/achievements/v3/10000points.gif" TITLE="10,000 Points" />
<?php } if($ac['points'] > 100000){?><img src="html/static/images/achievements/v3/100000points.gif" TITLE="100,000 Points" />
<?php } if($ac['points'] > 200000){?><img src="html/static/images/achievements/v3/200000points.gif" TITLE="200,000 Points" /><?php } ?>

<!-- Account related achievements -->

<?php if($as['lotto'] == 1){?><img src="html/static/images/achievements/v3/lottowinner.gif" TITLE="Lottery Winner" />
<?php } if($as['events'] == 1){?><img src="html/static/images/achievements/v3/events.gif" TITLE="All Events Complete" />
<?php } if($as['donator'] == 1){?><img src="html/static/images/achievements/v3/donator.gif" TITLE="Pok&eacute;mon Shqipe Donor" />
<?php } if($ac['money'] >= 1000000){?><img src="html/static/images/achievements/v3/millionaire.gif" TITLE="Millionaire" />

<?php } ?></p><br>
<?php include('pv_disconnect_from_db.php'); ?>
