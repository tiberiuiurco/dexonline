<?php
require_once("../../phplib/util.php"); 
util_assertModerator(PRIV_EDIT | PRIV_STRUCT);
util_assertNotMirror();

// We get some data as JSON because it is 2-dimensional (a list of lists)
// and PHP cannot parse the form data correctly.

// Lexem parameters
$lexemId = util_getRequestParameter('lexemId');
$lexemForm = util_getRequestParameter('lexemForm');
$lexemNumber = util_getRequestParameter('lexemNumber');
$lexemDescription = util_getRequestParameter('lexemDescription');
$lexemComment = util_getRequestParameter('lexemComment');
$needsAccent = util_getBoolean('needsAccent');
$main = util_getBoolean('main');
$stopWord = util_getBoolean('stopWord');
$hyphenations = util_getRequestParameter('hyphenations');
$pronunciations = util_getRequestParameter('pronunciations');
$entryId = util_getRequestParameter('entryId');
$tagIds = util_getRequestParameterWithDefault('tagIds', []);

// Paradigm parameters
$modelType = util_getRequestParameter('modelType');
$modelNumber = util_getRequestParameter('modelNumber');
$restriction = util_getRequestParameter('restriction');
$sourceIds = util_getRequestParameterWithDefault('sourceIds', []);
$notes = util_getRequestParameter('notes');
$isLoc = util_getBoolean('isLoc');

// Button parameters
$refreshButton = util_getBoolean('refreshButton');
$saveButton = util_getBoolean('saveButton');
$cloneButton = util_getBoolean('cloneButton');
$deleteButton = util_getBoolean('deleteButton');

$lexem = Lexem::get_by_id($lexemId);
$original = Lexem::get_by_id($lexemId); // Keep a copy so we can test whether certain fields have changed

if ($cloneButton) {
  $newLexem = $lexem->_clone();
  Log::notice("Cloned lexem {$lexem->id} ({$lexem->formNoAccent}), new id is {$newLexem->id}");
  util_redirect("lexemEdit.php?lexemId={$newLexem->id}");
}

if ($deleteButton) {
  $homonym = Model::factory('Lexem')
           ->where('formNoAccent', $lexem->formNoAccent)
           ->where_not_equal('id', $lexem->id)
           ->find_one();
  $lexem->delete();
  if ($homonym) {
    FlashMessage::add('Am șters lexemul și v-am redirectat la unul dintre omonime.', 'success');
    util_redirect("?lexemId={$homonym->id}");
  } else {
    FlashMessage::add('Am șters lexemul.', 'success');
    util_redirect('index.php');
  }
}

if ($refreshButton || $saveButton) {
  populate($lexem, $original, $lexemForm, $lexemNumber, $lexemDescription, $lexemComment,
           $needsAccent, $main, $stopWord, $hyphenations, $pronunciations, $entryId,
           $modelType, $modelNumber, $restriction, $notes, $isLoc, $sourceIds, $tagIds);

  if (validate($lexem, $original)) {
    // Case 1: Validation passed
    if ($saveButton) {
      if (($original->modelType == 'VT') && ($lexem->modelType != 'VT')) {
        $original->deleteParticiple();
      }
      if (in_array($original->modelType, ['V', 'VT']) &&
          !in_array($lexem->modelType, ['V', 'VT'])) {
        $original->deleteLongInfinitive();
      }
      $lexem->deepSave();
      $lexem->regenerateDependentLexems();

      Log::notice("Saved lexem {$lexem->id} ({$lexem->formNoAccent})");
      util_redirect("lexemEdit.php?lexemId={$lexem->id}");
    }
  } else {
    // Case 2: Validation failed
  }
  // Case 1-2: Page was submitted
} else {
  // Case 3: First time loading this page
  $lexem->loadInflectedFormMap();

  RecentLink::add("Lexem: $lexem (ID={$lexem->id})");
}

$definitions = Definition::loadByEntryId($lexem->entryId);
$searchResults = SearchResult::mapDefinitionArray($definitions);

$canEdit = [
  'general' => util_isModerator(PRIV_EDIT),
  'description' => util_isModerator(PRIV_EDIT),
  'form' => !$lexem->isLoc || util_isModerator(PRIV_LOC),
  'hyphenations' => util_isModerator(PRIV_STRUCT | PRIV_EDIT),
  'loc' => (int)util_isModerator(PRIV_LOC),
  'paradigm' => util_isModerator(PRIV_EDIT),
  'pronunciations' => util_isModerator(PRIV_STRUCT | PRIV_EDIT),
  'sources' => util_isModerator(PRIV_LOC | PRIV_EDIT),
  'stopWord' => util_isModerator(PRIV_ADMIN),
  'tags' => util_isModerator(PRIV_LOC | PRIV_EDIT),
];

// Prepare a list of model numbers, to be used in the paradigm drop-down.
$models = FlexModel::loadByType($lexem->modelType);

SmartyWrap::assign('lexem', $lexem);
SmartyWrap::assign('homonyms', Model::factory('Lexem')->where('formNoAccent', $lexem->formNoAccent)->where_not_equal('id', $lexem->id)->find_many());
SmartyWrap::assign('searchResults', $searchResults);
SmartyWrap::assign('modelTypes', Model::factory('ModelType')->order_by_asc('code')->find_many());
SmartyWrap::assign('models', $models);
SmartyWrap::assign('canEdit', $canEdit);
SmartyWrap::addCss('paradigm', 'admin');
SmartyWrap::addJs('select2Dev', 'modelDropdown');
SmartyWrap::display('admin/lexemEdit.tpl');

/**************************************************************************/

// Populate lexem fields from request parameters.
function populate(&$lexem, &$original, $lexemForm, $lexemNumber, $lexemDescription, $lexemComment,
                  $needsAccent, $main, $stopWord, $hyphenations, $pronunciations, $entryId,
                  $modelType, $modelNumber, $restriction, $notes, $isLoc, $sourceIds, $tagIds) {
  $lexem->setForm(AdminStringUtil::formatLexem($lexemForm));
  $lexem->number = $lexemNumber;
  $lexem->description = AdminStringUtil::internalize($lexemDescription, false);
  $lexem->comment = trim(AdminStringUtil::internalize($lexemComment, false));
  // Sign appended comments
  if (StringUtil::startsWith($lexem->comment, $original->comment) &&
      $lexem->comment != $original->comment &&
      !StringUtil::endsWith($lexem->comment, ']]')) {
    $lexem->comment .= " [[" . session_getUser() . ", " . strftime("%d %b %Y %H:%M") . "]]";
  }
  $lexem->noAccent = !$needsAccent;
  $lexem->main = $main;
  $lexem->stopWord = $stopWord;
  $lexem->hyphenations = $hyphenations;
  $lexem->pronunciations = $pronunciations;
  $lexem->entryId = $entryId;

  $lexem->modelType = $modelType;
  $lexem->modelNumber = $modelNumber;
  $lexem->restriction = $restriction;
  $lexem->notes = $notes;
  $lexem->isLoc = $isLoc;

  // create LexemSources
  $lexemSources = [];
  foreach ($sourceIds as $sourceId) {
    $ls = Model::factory('LexemSource')->create();
    $ls->sourceId = $sourceId;
    $lexemSources[] = $ls;
  }
  $lexem->setLexemSources($lexemSources);

  // create LexemTags
  $lexemTags = [];
  foreach ($tagIds as $tagId) {
    $lt = Model::factory('LexemTag')->create();
    $lt->tagId = $tagId;
    $lexemTags[] = $lt;
  }
  $lexem->setLexemTags($lexemTags);

  $lexem->generateInflectedFormMap();
}

function validate($lexem, $original) {
  if (!$lexem->form) {
    FlashMessage::add('Forma nu poate fi vidă.');
  }

  $numAccents = mb_substr_count($lexem->form, "'");
  // Note: we allow multiple accents for lexems like hárcea-párcea
  if ($numAccents && $lexem->noAccent) {
    FlashMessage::add('Ați indicat că lexemul nu necesită accent, dar forma conține un accent.');
  } else if (!$numAccents && !$lexem->noAccent) {
    FlashMessage::add('Adăugați un accent sau debifați câmpul „Necesită accent”.');
  }

  // Gather all different restriction - model type pairs
  $pairs = Model::factory('ConstraintMap')
         ->table_alias('cm')
         ->select_expr('binary cm.code', 'restr')
         ->select('mt.code', 'modelType')
         ->distinct()
         ->join('Inflection', ['cm.inflectionId', '=', 'i.id'], 'i')
         ->join('ModelType', ['i.modelType', '=', 'mt.canonical'], 'mt')
         ->find_array();
  $restrMap = [];
  foreach ($pairs as $p) {
    $restrMap[$p['restr']][$p['modelType']] = true;
  }

  for ($i = 0; $i < mb_strlen($lexem->restriction); $i++) {
    $c = StringUtil::getCharAt($lexem->restriction, $i);
    if (!isset($restrMap[$c])) {
      FlashMessage::add("Restricția <strong>$c</strong> este nedefinită.");
    } else if (!isset($restrMap[$c][$lexem->modelType])) {
      FlashMessage::add("Restricția <strong>$c</strong> nu se aplică modelului <strong>{$lexem->modelType}.</strong>");
    }
  }
  
  $ifs = $lexem->generateInflectedForms();
  if (!is_array($ifs)) {
    $infl = Inflection::get_by_id($ifs);
    FlashMessage::add(sprintf("Nu pot genera flexiunea '%s' conform modelului %s%s",
                              htmlentities($infl->description), $lexem->modelType, $lexem->modelNumber));
  }

  return !FlashMessage::hasErrors();
}

?>
