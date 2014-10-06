<?php

final class PhortuneProductEditController extends PhabricatorController {

  private $productID;

  public function willProcessRequest(array $data) {
    $this->productID = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->productID) {
      $product = id(new PhortuneProductQuery())
        ->setViewer($user)
        ->withIDs(array($this->productID))
        ->executeOne();
      if (!$product) {
        return new Aphront404Response();
      }

      $is_create = false;
      $cancel_uri = $this->getApplicationURI(
        'product/view/'.$this->productID.'/');
    } else {
      $product = PhortuneProduct::initializeNewProduct();
      $is_create = true;
      $cancel_uri = $this->getApplicationURI('product/');
    }

    $v_name = $product->getProductName();
    $v_price = $product->getPriceAsCurrency()->formatForDisplay();
    $display_price = $v_price;

    $e_name = true;
    $e_price = true;
    $errors = array();

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      if (!strlen($v_name)) {
        $e_name = pht('Required');
        $errors[] = pht('Product must have a name.');
      } else {
        $e_name = null;
      }

      $display_price = $request->getStr('price');
      try {
        $v_price = PhortuneCurrency::newFromUserInput($user, $display_price)
          ->serializeForStorage();
        $e_price = null;
      } catch (Exception $ex) {
        $errors[] = pht('Price should be formatted as: $1.23');
        $e_price = pht('Invalid');
      }

      if (!$errors) {
        $xactions = array();

        $xactions[] = id(new PhortuneProductTransaction())
          ->setTransactionType(PhortuneProductTransaction::TYPE_NAME)
          ->setNewValue($v_name);

        $xactions[] = id(new PhortuneProductTransaction())
          ->setTransactionType(PhortuneProductTransaction::TYPE_PRICE)
          ->setNewValue($v_price);

        $editor = id(new PhortuneProductEditor())
          ->setActor($user)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request);

        $editor->applyTransactions($product, $xactions);

        return id(new AphrontRedirectResponse())->setURI(
          $this->getApplicationURI('product/view/'.$product->getID().'/'));
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setErrors($errors);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Price'))
          ->setName('price')
          ->setValue($display_price)
          ->setError($e_price))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(
            $is_create
              ? pht('Create Product')
              : pht('Save Product'))
          ->addCancelButton($cancel_uri));

    $title = pht('Edit Product');
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Products'),
      $this->getApplicationURI('product/'));
    $crumbs->addTextCrumb(
      $is_create ? pht('Create') : pht('Edit'),
      $request->getRequestURI());

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edit Product'))
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}
